<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use Phpactor\LanguageServerProtocol\Position;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\LineCol;

class PositionConverter
{
    public static function byteOffsetToPosition(ByteOffset $offset, string $text): Position
    {
        $lineCol = LineCol::fromByteOffset($text, $offset);

        return new Position($lineCol->line(), $lineCol->col());
    }

    public static function positionToByteOffset(Position $position, string $text): ByteOffset
    {
        $lineCol = new LineCol($position->line, $position->character);

        return $lineCol->toByteOffset($text);
    }
}
