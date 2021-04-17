<?php

namespace Phpactor\Extension\LanguageServerRename\Model\FileRenamer;

use Amp\Promise;
use Amp\Success;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer;
use Phpactor\TextDocument\TextDocumentUri;

class NullFileRenamer implements FileRenamer
{
    public function renameFile(TextDocumentUri $from, TextDocumentUri $to): Promise
    {
        return new Success();
    }
}
