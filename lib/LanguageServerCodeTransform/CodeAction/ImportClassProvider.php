<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Generator;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;

class ImportClassProvider implements CodeActionProvider
{
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * {@inheritDoc}
     */
    public function actionsFor(TextDocumentItem $item, Range $range): Generator
    {
    }
}
