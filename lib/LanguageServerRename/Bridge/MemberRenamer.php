<?php

namespace Phpactor\Extension\LanguageServerRename\Bridge;

use Generator;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

class MemberRenamer implements Renamer
{
    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        return null;
    }
    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        return;
        yield;
    }
}
