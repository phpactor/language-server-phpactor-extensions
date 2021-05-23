<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Amp\Promise;
use Phpactor\CodeTransform\Domain\NameWithByteOffset;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportAllUnresolvedNamesCommand;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImport\CandidateFinder;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use function Amp\call;
use function Amp\delay;

class ImportNameProvider implements CodeActionProvider, DiagnosticsProvider
{
    /**
     * @var CandidateFinder
     */
    private $finder;


    public function __construct(CandidateFinder $finder)
    {
        $this->finder = $finder;
    }

    public function provideActionsFor(TextDocumentItem $item, Range $range): Promise
    {
        return call(function () use ($item) {
            $actions = [];
            foreach ($this->finder->importCandidates($item) as $candidate) {
                $actions[] = $this->codeActionForFqn($candidate->unresolvedName(), $candidate->candidateFqn(), $item);
                yield delay(1);
            }

            if (count($actions) > 1) {
                $actions[] = $this->addImportAllAction($item);
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
            foreach ($this->finder->unresolved($textDocument) as $unresolvedName) {
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

        $candidates = iterator_to_array($this->finder->importCandidates($item));

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

    private function codeActionForFqn(NameWithByteOffset $unresolvedName, string $fqn, TextDocumentItem $item): CodeAction
    {
        return CodeAction::fromArray([
            'title' => sprintf(
                'Import %s "%s"',
                $unresolvedName->type(),
                $fqn
            ),
            'kind' => 'quickfix.import_class',
            'isPreferred' => false,
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

    private function addImportAllAction(TextDocumentItem $item): CodeAction
    {
        return CodeAction::fromArray([
            'title' => sprintf(
                'Import all unresolved names',
            ),
            'kind' => 'quickfix.import_all_unresolved_names',
            'isPreferred' => true,
            'diagnostics' => [],
            'command' => new Command(
                'Import all unresolved names',
                ImportAllUnresolvedNamesCommand::NAME,
                [
                    $item->uri,
                ]
            )
        ]);
    }
}
