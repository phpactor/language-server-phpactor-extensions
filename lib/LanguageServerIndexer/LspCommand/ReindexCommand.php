<?php

namespace Phpactor\Extension\LanguageServerIndexer\LspCommand;

use Amp\Promise;
use Phpactor\Indexer\Model\Indexer;

class ReindexCommand
{
    /**
     * @var Indexer
     */
    private $indexer;

    public function __construct(Indexer $indexer)
    {
        $this->indexer = $indexer;
    }

    /**
     * @return Promise<void>
     */
    public function __invoke(): Promise
    {
        $this->indexer->reset();
    }
}
