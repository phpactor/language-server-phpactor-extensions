<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter;

use Generator;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

class VariableRenamer implements Renamer
{
    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
    }

}