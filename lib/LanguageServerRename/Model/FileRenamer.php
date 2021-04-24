<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Amp\Promise;
use Phpactor\TextDocument\TextDocumentUri;

interface FileRenamer
{
    /**
     * @return Promise<LocatedTextEditsMap>
     */
    public function renameFile(TextDocumentUri $from, TextDocumentUri $to): Promise;
}
