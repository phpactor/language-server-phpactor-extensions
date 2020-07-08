<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\TextDocument\ByteOffset;

class OffsetConverter
{
    public function offsetToPosition(string $text, ByteOffset $offset): Position
    {
        return OffsetHelper::offsetToPosition($text, $offset->toInt());
    }
    
    public function toOffset(Position $position, string $content): ByteOffset
    {
        $lines = explode("\n", $content);
        $slice = array_slice($lines, 0, $position->line);

        return ByteOffset::fromInt((int) array_sum(array_map('strlen', $slice)) + count($slice) + $position->character);
    }
}
