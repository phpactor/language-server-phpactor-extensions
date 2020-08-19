<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Amp\Promise;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\CodeTransform\Domain\Transformer;
use Phpactor\Extension\LanguageServerBridge\Converter\TextDocumentConverter;
use Phpactor\Extension\LanguageServerCodeTransform\Converter\DiagnosticsConverter;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use function Amp\call;

class TransformerCodeAction implements DiagnosticsProvider
{
    /**
     * @var Transformer
     */
    private $transformer;

    /**
     * @var string
     */
    private $kind;

    public function __construct(Transformer $transformer, string $kind)
    {
        $this->transformer = $transformer;
        $this->kind = $kind;
    }

    /**
     * {@inheritDoc}
     */
    public function kinds(): array
    {
        return [
            $this->kind
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function provideDiagnostics(TextDocumentItem $textDocument): Promise
    {
        return call(function () use ($textDocument) {
            $phpactorTextDocument = TextDocumentConverter::fromLspTextItem($textDocument);

            return DiagnosticsConverter::toLspDiagnostics(
                $phpactorTextDocument,
                $this->transformer->diagnostics(
                    SourceCode::fromStringAndPath($textDocument->text, $phpactorTextDocument->uri()->path())
                )
            );
        });
    }
}
