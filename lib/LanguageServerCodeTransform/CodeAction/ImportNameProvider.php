<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Amp\Promise;
use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\CodeTransform\Domain\NameWithByteOffset;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Indexer\Model\Query\Criteria;
use Phpactor\Indexer\Model\Record\HasFullyQualifiedName;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\WorseReflection\Core\Exception\NotFound;
use Phpactor\WorseReflection\Core\Reflector\FunctionReflector;
use function Amp\call;
use function Amp\delay;

class ImportNameProvider implements CodeActionProvider, DiagnosticsProvider
{
    /**
     * @var UnresolvableClassNameFinder
     */
    private $finder;

    /**
     * @var SearchClient
     */
    private $client;

    /**
     * @var bool
     */
    private $importGlobals;

    /**
     * @var FunctionReflector
     */
    private $functionReflector;

    public function __construct(UnresolvableClassNameFinder $finder, FunctionReflector $functionReflector, SearchClient $client, bool $importGlobals = false)
    {
        $this->finder = $finder;
        $this->client = $client;
        $this->importGlobals = $importGlobals;
        $this->functionReflector = $functionReflector;
    }

    public function provideActionsFor(TextDocumentItem $item, Range $range): Promise
    {
        return call(function () use ($item) {
            $unresolvedNames = $this->finder->find(
                TextDocumentBuilder::create($item->text)->uri($item->uri)->language('php')->build()
            );

            $actions = [];
            foreach ($unresolvedNames as $unresolvedName) {
                if ($this->isUnresolvedGlobalFunction($unresolvedName)) {
                    if (false === $this->importGlobals) {
                        continue;
                    }
                    $actions[] = $this->codeActionForFqn($unresolvedName, $unresolvedName->name()->head()->__toString(), $item);
                    continue;
                }
                assert($unresolvedName instanceof NameWithByteOffset);

                $candidates = $this->findCandidates($unresolvedName);

                foreach ($candidates as $candidate) {
                    assert($candidate instanceof HasFullyQualifiedName);

                    $fqn = $candidate->fqn()->__toString();
                    $actions[] = $this->codeActionForFqn($unresolvedName, $fqn, $item);
                    yield delay(1);
                }
            }

            return $actions;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function kinds(): array
    {
        return [
            'quickfix.import_class'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function provideDiagnostics(TextDocumentItem $textDocument): Promise
    {
        return call(function () use ($textDocument) {
            $diagnostics = [];
            $unresolvedNames = $this->finder->find(
                TextDocumentBuilder::create($textDocument->text)->uri($textDocument->uri)->language('php')->build()
            );

            foreach ($unresolvedNames as $unresolvedName) {
                if (false === $this->importGlobals && $this->isUnresolvedGlobalFunction($unresolvedName)) {
                    continue;
                }
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->diagnosticsFromUnresolvedName($unresolvedName, $textDocument)
                );
            }

            return $diagnostics;
        });
    }

    private function diagnosticsFromUnresolvedName(NameWithByteOffset $unresolvedName, TextDocumentItem $item): array
    {
        $range = new Range(
            PositionConverter::byteOffsetToPosition($unresolvedName->byteOffset(), $item->text),
            PositionConverter::intByteOffsetToPosition(
                $unresolvedName->byteOffset()->toInt() + strlen($unresolvedName->name()->head()->__toString()),
                $item->text
            )
        );

        $candidates = $this->findCandidates($unresolvedName);

        if (count($candidates) === 0) {
            return [
                new Diagnostic(
                    $range,
                    sprintf(
                        '%s "%s" does not exist',
                        ucfirst($unresolvedName->type()),
                        $unresolvedName->name()->head()->__toString()
                    ),
                    DiagnosticSeverity::ERROR,
                    null,
                    'phpactor'
                )
            ];
        }

        return [
            new Diagnostic(
                $range,
                sprintf(
                    '%s "%s" has not been imported',
                    ucfirst($unresolvedName->type()),
                    $unresolvedName->name()->head()->__toString()
                ),
                DiagnosticSeverity::HINT,
                null,
                'phpactor'
            )
        ];
    }

    private function findCandidates(NameWithByteOffset $unresolvedName): array
    {
        $candidates = [];
        foreach ($this->client->search(Criteria::and(
            Criteria::or(
                Criteria::isClass(),
                Criteria::isFunction()
            ),
            Criteria::exactShortName($unresolvedName->name()->head()->__toString())
        )) as $candidate) {
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    private function isUnresolvedGlobalFunction(NameWithByteOffset $unresolvedName): bool
    {
        if ($unresolvedName->type() !== NameWithByteOffset::TYPE_FUNCTION) {
            return false;
        }

        try {
            $this->functionReflector->sourceCodeForFunction(
                $unresolvedName->name()->head()->__toString()
            );
            return true;
        } catch (NotFound $notFound) {
        }
        return false;
    }

    private function codeActionForFqn(NameWithByteOffset $unresolvedName, string $fqn, TextDocumentItem $item): CodeAction
    {
        return CodeAction::fromArray([
            'title' => sprintf(
                'Import %s "%s"',
                $unresolvedName->type(),
                $fqn
            ),
            'kind' => 'quickfix.import_class',
            'isPreferred' => true,
            'diagnostics' => $this->diagnosticsFromUnresolvedName($unresolvedName, $item),
            'command' => new Command(
                'Import name',
                ImportNameCommand::NAME,
                [
                    $item->uri,
                    $unresolvedName->byteOffset()->toInt(),
                    $unresolvedName->type(),
                    $fqn
                ]
            )
        ]);
    }
}
