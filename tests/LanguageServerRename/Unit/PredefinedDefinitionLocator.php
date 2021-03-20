<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentUri;

class PredefinedDefinitionLocator implements DefinitionLocator
{
    /**
     * @var ?ByteOffset
     */
    private $definition;
    /**
     * @var ?string
     */
    private $uri;

    public function __construct(?ByteOffset $definition, ?string $uri)
    {
        $this->definition = $definition;
        $this->uri = $uri;
    }

    public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
    {
        if ($this->definition !== null) {
            return new DefinitionLocation(
                TextDocumentUri::fromString($this->uri),
                $this->definition
            );
        } else {
            throw new CouldNotLocateDefinition();
        }
    }
}
