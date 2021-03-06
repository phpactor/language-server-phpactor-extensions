<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Generator;

class PredefinedReferenceFinder implements ReferenceFinder
{
    /** @var array */
    private $references;
    /** @var TextDocument */
    private $textDocument;

    public function __construct(array $references, TextDocument $textDocument)
    {
        $this->references = $references;
        $this->textDocument = $textDocument;
    }

    public function findReferences(TextDocument $document, ByteOffset $byteOffset): Generator
    {
        foreach ($this->references as $offset) {
            yield PotentialLocation::surely(
                new Location($this->textDocument->uri(), $offset)
            );
        }
    }
}
