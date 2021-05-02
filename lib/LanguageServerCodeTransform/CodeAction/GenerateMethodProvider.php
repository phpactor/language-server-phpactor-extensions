<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Amp\Promise;
use Amp\Success;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\GenerateMethodCommand;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\CodeAction\CodeActionProvider;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use Phpactor\TextDocument\ByteOffset;
use function Amp\call;
use function array_filter;

class GenerateMethodProvider implements DiagnosticsProvider, CodeActionProvider
{
    public const KIND = 'quickfix.generate_method';
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
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
    public function provideDiagnostics(TextDocumentItem $textDocument): Promise
    {
        return new Success($this->getDiagnostics($textDocument));
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

            $diagnostics = $this->getDiagnostics($textDocument);

            if (empty($diagnostics)) {
                return [];
            }

            $diagnostics = array_values(
                array_filter($diagnostics, function (Diagnostic $diag) use ($range) {
                    return (
                        $diag->range->start->line <= $range->start->line &&
                        $diag->range->start->character <= $range->start->character &&
                        $diag->range->end->line >= $range->end->line &&
                        $diag->range->end->character >= $range->end->character
                    );
                })
            );

            if (count($diagnostics) === 0) {
                return [];
            }

            return [
                CodeAction::fromArray([
                    'title' =>  'Generate method (if not exists)',
                    'kind' => self::KIND,
                    'diagnostics' => $diagnostics,
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
    /**
     * @return array<Diagnostic>
     */
    private function getDiagnostics(TextDocumentItem $textDocument): array
    {
        $node = $this->parser->parseSourceFile($textDocument->text);
        $diagnostics = [];
        foreach ($node->getDescendantNodes() as $node) {
            if ((!$node instanceof CallExpression)) {
                continue;
            }
            assert($node instanceof CallExpression);

            $memberName = null;
            if ($node->callableExpression instanceof MemberAccessExpression) {
                $memberName = $node->callableExpression->memberName;
            } elseif ($node->callableExpression instanceof ScopedPropertyAccessExpression) {
                $memberName = $node->callableExpression->memberName;
            }
            
            if (!($memberName instanceof Token)) {
                continue;
            }

            assert($memberName instanceof Token);
            
            $diagnostics[] = new Diagnostic(
                new Range(
                    PositionConverter::byteOffsetToPosition(
                        ByteOffset::fromInt($memberName->start),
                        $textDocument->text
                    ),
                    PositionConverter::byteOffsetToPosition(
                        ByteOffset::fromInt($memberName->start + $memberName->length),
                        $textDocument->text
                    ),
                ),
                'Generate method',
                DiagnosticSeverity::INFORMATION,
                null,
                'phpactor'
            );
        }

        usort($diagnostics, function (Diagnostic $a, Diagnostic $b) {
            if ($a->range->start->line > $b->range->start->line) {
                return 1;
            }
            
            if ($a->range->start->line < $b->range->start->line) {
                return -1;
            }
            
            if ($a->range->start->character > $b->range->start->character) {
                return 1;
            }

            if ($a->range->start->character < $b->range->start->character) {
                return -1;
            }
            
            return 0;
        });

        return $diagnostics;
    }
}
