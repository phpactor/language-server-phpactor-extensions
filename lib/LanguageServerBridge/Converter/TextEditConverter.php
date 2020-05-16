<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use LanguageServerProtocol\TextEdit as LspTextEdit;
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
    public function toLspTextEdits(TextEdits $textEdits): array 
    {
        return [];
    }
}
