<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Amp\Promise;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\GenerateMethodCommand;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider;
use function Amp\call;

class GenerateMethodProvider implements CodeActionProvider
{
    public const KIND = 'quickfix.generate_method';

    public function __construct()
    {
    }
    /**
     * {@inheritDoc}
     */
    public function kinds(): array
    {
        return [
             self::KIND
         ];
    }
    /**
     * {@inheritDoc}
     */
    public function provideActionsFor(TextDocumentItem $textDocument, Range $range): Promise
    {
        return call(function () use ($textDocument, $range) {
            if ($range->start != $range->end) {
                return [];
            }

            return [
                CodeAction::fromArray([
                    'title' =>  'Generate method',
                    'kind' => self::KIND,
                    'diagnostics' => [], //$this->getDiagnostics($textDocument),
                    'command' => new Command(
                        'Generate method',
                        GenerateMethodCommand::NAME,
                        [
                            $textDocument->uri,
                            PositionConverter::positionToByteOffset($range->start, $textDocument->text)->toInt()
                        ]
                    )
                ])
            ];
        });
    }
}
