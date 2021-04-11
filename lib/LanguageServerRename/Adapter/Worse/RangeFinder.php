<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse;

use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

interface RangeFinder
{
    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange;
}
