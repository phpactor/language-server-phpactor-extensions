<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit as LspTextEdit;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class TextEditConverter
{
    /**
     * @var LocationConverter
     */
    private $locationConverter;

    public function __construct(LocationConverter $locationConverter)
    {
        $this->locationConverter = $locationConverter;
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
