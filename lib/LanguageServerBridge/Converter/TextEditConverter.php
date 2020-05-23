<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use LanguageServerProtocol\Range;
use LanguageServerProtocol\TextEdit as LspTextEdit;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class TextEditConverter
{
    /**
     * @var LocationConverter
     */
    private $locationConverter;

    /**
     * @var OffsetConverter
     */
    private $offsetConverter;

    public function __construct(LocationConverter $locationConverter, ?OffsetConverter $offsetConverter = null)
    {
        $this->locationConverter = $locationConverter;
        $this->offsetConverter = $offsetConverter ?: new OffsetConverter();
    }

    /**
     * @param TextEdits<TextEdit> $textEdits
     * @return array<LspTextEdit>
     */
    public function toLspTextEdits(TextEdits $textEdits, string $text): array
    {
        $edits = [];
        foreach ($textEdits as $textEdit) {
            $range = new Range(
                $this->offsetConverter->offsetToPosition($text, $textEdit->start()),
                $this->offsetConverter->offsetToPosition($text, $textEdit->end())
            );
            $edits[] = new LspTextEdit($range, $textEdit->replacement());
        }

        return $edits;
    }
}
