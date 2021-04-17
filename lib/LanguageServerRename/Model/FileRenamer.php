<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Amp\Promise;
use Phpactor\TextDocument\TextDocumentUri;

interface FileRenamer
{
    public function renameFile(TextDocumentUri $from, TextDocumentUri $to): Promise;
}
