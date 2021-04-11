<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Generator;
use Phpactor\TextDocument\TextDocumentUri;

class PredefinedReferenceFinder implements ReferenceFinder
{
    /**
     * @var array<string,ByteOffset[]>
     */
    private $references;
    /**
     * @var array<string,string>
     */
    private $sources;

    public function __construct(array $references)
    {
        $this->references = $references;
    }

    public function findReferences(TextDocument $document, ByteOffset $byteOffset): Generator
    {
        foreach ($this->references as $uri=>$offsets) {
            $uriObj = TextDocumentUri::fromString($uri);
            foreach ($offsets as $offset) {
                yield PotentialLocation::surely(
                    new Location($uriObj, $offset)
                );
            }
        }
    }
}
