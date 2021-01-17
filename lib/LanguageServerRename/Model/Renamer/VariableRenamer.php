<?php

namespace Phpactor\Extension\LanguageServerRename\Model\Renamer;

use Generator;
use Microsoft\PhpParser\Node\Expression\Variable;
use Phpactor\Extension\LanguageServerRename\Model\Renamer2;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

class VariableRenamer implements Renamer2
{
    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        // if ($node instanceof Variable) {
        // }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): ?Generator
    {
        return null;
    }
}
