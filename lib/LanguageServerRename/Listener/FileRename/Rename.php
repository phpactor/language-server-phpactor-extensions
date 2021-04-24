<?php

namespace Phpactor\Extension\LanguageServerRename\Listener\FileRename;

use Phpactor\TextDocument\TextDocumentUri;

class Rename
{
    /**
     * @var TextDocumentUri
     */
    public $from;

    /**
     * @var TextDocumentUri
     */
    public $to;

    public function __construct(TextDocumentUri $from, TextDocumentUri $to)
    {
        $this->from = $from;
        $this->to = $to;
    }
}
