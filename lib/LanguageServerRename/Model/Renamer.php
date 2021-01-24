<?php
namespace Phpactor\Extension\LanguageServerRename\Model;

use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

interface Renamer
{
    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange;
    /**
     * @return \Generator<RenameResult>
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): ?\Generator;
}
