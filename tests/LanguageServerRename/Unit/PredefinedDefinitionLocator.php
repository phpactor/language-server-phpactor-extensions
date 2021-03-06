<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;

class PredefinedDefinitionLocator implements DefinitionLocator
{
    /** @var ?ByteOffset */
    private $definition;
    /** @var TextDocument */
    private $textDocument;

    public function __construct(?ByteOffset $definition, TextDocument $textDocument)
    {
        $this->definition = $definition;
        $this->textDocument = $textDocument;
    }

    public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
    {
        if ($this->definition !== null) {
            return new DefinitionLocation(
                $this->textDocument->uri(),
                $this->definition
            );
        } else {
            throw new CouldNotLocateDefinition();
        }
    }
}
