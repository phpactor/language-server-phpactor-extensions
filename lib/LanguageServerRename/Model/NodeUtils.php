<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Microsoft\PhpParser\Token;
use Phpactor\TextDocument\ByteOffsetRange;

class NodeUtils
{
    public function getTokenNameText(Token $token, string $fileContents): string
    {
        $text = (string)$token->getText($fileContents);
        if (mb_substr((string)$token->getText($fileContents), 0, 1) == '$') {
            $text = mb_substr($text, 1);
        }
        return $text;
    }

    public function getTokenNameRange(Token $token, string $fileContents): ByteOffsetRange
    {
        $range = $this->getTokenRange($token);
        if (mb_substr((string)$token->getText($fileContents), 0, 1) == '$') {
            $range = ByteOffsetRange::fromInts(
                $range->start()->toInt() + 1,
                $range->end()->toInt()
            );
        }
        return $range;
    }

    public function getRangeText(ByteOffsetRange $range, string $source): string
    {
        return substr(
            $source,
            $range->start()->toInt(),
            $range->end()->toInt() - $range->start()->toInt()
        );
    }
    
    public function getTokenRange(Token $token): ByteOffsetRange
    {
        return ByteOffsetRange::fromInts(
            $token->getStartPosition(),
            $token->getEndPosition()
        );
    }
}
