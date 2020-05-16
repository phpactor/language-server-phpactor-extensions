<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use LanguageServerProtocol\Position;
use Phpactor\TextDocument\ByteOffset;

class OffsetConverter
{
    public function offsetToPosition(string $text, ByteOffset $offset): Position
    {
        $text = substr($text, 0, $offset->toInt());
        $line = mb_substr_count($text, PHP_EOL);

        if ($line === 0) {
            return new Position($line, mb_strlen($text));
        }

        $lastNewLinePos = mb_strrpos($text, PHP_EOL);
        $remainingLine = mb_substr($text, $lastNewLinePos + mb_strlen(PHP_EOL));
        $char = mb_strlen($remainingLine);

        return new Position($line, $char);
    }
}
