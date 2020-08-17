<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Generator;
use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\CodeTransform\Domain\NameWithByteOffset;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider;
use Phpactor\TextDocument\TextDocumentBuilder;

class ImportClassProvider implements CodeActionProvider
{
    /**
     * @var UnresolvableClassNameFinder
     */
    private $finder;

    public function __construct(UnresolvableClassNameFinder $finder)
    {
        $this->finder = $finder;
    }

    public function provideActionsFor(TextDocumentItem $item, Range $range): Generator
    {
        $unresolvedNames = $this->finder->find(
            TextDocumentBuilder::create($item->text)->uri($item->uri)->language('php')->build()
        );

        foreach ($unresolvedNames as $unresolvedName) {
            assert($unresolvedName instanceof NameWithByteOffset);

            $range = new Range(
                PositionConverter::byteOffsetToPosition($unresolvedName->byteOffset(), $item->text),
                PositionConverter::intByteOffsetToPosition(
                    $unresolvedName->byteOffset()->toInt() + strlen($unresolvedName->name()->__toString()),
                    $item->text
                )
            );

            yield CodeAction::fromArray([
                'title' => sprintf('Import "%s"', $unresolvedName->name()->__toString()),
                'kind' => 'quickfix.import_class',
                'diagnostics' => [
                    new Diagnostic(
                        $range,
                        sprintf('Class "%s" has not been imported', $unresolvedName->name()->__toString()),
                        DiagnosticSeverity::ERROR
                    )
                ],
                'command' => new Command(
                    'Import name',
                    ImportNameCommand::NAME,
                    [
                        $item->uri,
                        $unresolvedName->byteOffset()->toInt(),
                        'class',
                        $unresolvedName->name()->__toString()
                    ]
                )
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */

    /**
     * {@inheritDoc}
     */
    public function kinds(): array
    {
        return [
            'fix.import'
        ];
    }
}
