<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdits;

class RenameResult
{
    /**
    * @var TextEdits
    */
    private $textEdits;
    /**
    * @var TextDocumentUri
    */
    private $documentUri;

    public function __construct(TextEdits $textEdits, TextDocumentUri $documentUri)
    {
        $this->textEdits = $textEdits;
        $this->documentUri = $documentUri;
    }
}
