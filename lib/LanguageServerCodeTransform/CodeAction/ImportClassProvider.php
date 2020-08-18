<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Amp\Promise;
use Generator;
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
use function Amp\call;

class ImportClassProvider implements CodeActionProvider, DiagnosticsProvider
{
    /**
     * @var UnresolvableClassNameFinder
     */
    private $finder;

    /**
     * @var SearchClient
     */
    private $client;

    public function __construct(UnresolvableClassNameFinder $finder, SearchClient $client)
    {
        $this->finder = $finder;
        $this->client = $client;
    }

    public function provideActionsFor(TextDocumentItem $item, Range $range): Generator
    {
        $unresolvedNames = $this->finder->find(
            TextDocumentBuilder::create($item->text)->uri($item->uri)->language('php')->build()
        );

        foreach ($unresolvedNames as $unresolvedName) {
            assert($unresolvedName instanceof NameWithByteOffset);

            $candidates = $this->client->search(
                Criteria::exactShortName($unresolvedName->name()->head()->__toString())
            );

            foreach ($candidates as $candidate) {
                assert($candidate instanceof HasFullyQualifiedName);
                yield CodeAction::fromArray([
                    'title' => sprintf(
                        'Import %s "%s"',
                        $unresolvedName->type(),
                        $candidate->fqn()->__toString()
                    ),
                    'kind' => 'quickfix.import_class',
                    'isPreferred' => true,
                    'diagnostics' => [
                        $this->diagnosticFromUnresolvedName($unresolvedName, $item)
                    ],
                    'command' => new Command(
                        'Import name',
                        ImportNameCommand::NAME,
                        [
                            $item->uri,
                            $unresolvedName->byteOffset()->toInt(),
                            $unresolvedName->type(),
                            $candidate->fqn()->__toString()
                        ]
                    )
                ]);
            }
        }
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
                $diagnostics[] = $this->diagnosticFromUnresolvedName($unresolvedName, $textDocument);
            }

            return $diagnostics;
        });
    }

    private function diagnosticFromUnresolvedName(NameWithByteOffset $unresolvedName, TextDocumentItem $item): Diagnostic
    {
        $range = new Range(
            PositionConverter::byteOffsetToPosition($unresolvedName->byteOffset(), $item->text),
            PositionConverter::intByteOffsetToPosition(
                $unresolvedName->byteOffset()->toInt() + strlen($unresolvedName->name()->head()->__toString()),
                $item->text
            )
        );

        $candidates = $this->client->search(
            Criteria::exactShortName($unresolvedName->name()->head()->__toString())
        );

        if (count(iterator_to_array($candidates)) === 0) {
            return new Diagnostic(
                $range,
                sprintf(
                    '%s "%s" does not exist',
                    ucfirst($unresolvedName->type()),
                    $unresolvedName->name()->head()->__toString()
                ),
                DiagnosticSeverity::ERROR,
                null,
                'phpactor'
            );
        }

        return new Diagnostic(
            $range,
            sprintf(
                '%s "%s" has not been imported',
                ucfirst($unresolvedName->type()),
                $unresolvedName->name()->head()->__toString()
            ),
            DiagnosticSeverity::HINT,
            null,
            'phpactor'
        );
    }
}
