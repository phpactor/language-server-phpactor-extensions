<?php

namespace Phpactor\Extension\LanguageServerRename\Model\Renamer;

use Generator;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\Util\WordAtOffset;

class InMemoryRenamer implements Renamer
{
    /**
     * @var ByteOffsetRange
     */
    private $range;
    /**
     * @var array
     */
    private $results;

    public function __construct(?ByteOffsetRange $range, array $results = [])
    {
        $this->results = $results;
        $this->range = $range;
    }

    public function setRange(ByteOffsetRange $range): void
    {
        $this->range = $range;
    }

    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        return $this->range;
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        yield from $this->results;
    }
}
