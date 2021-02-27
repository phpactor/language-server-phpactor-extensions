<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter;

use Generator;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

class VariableRenamer implements Renamer
{
    /**
     * @var RenameLocationsProvider
     */
    private $renameLocations;

    public function __construct(RenameLocationsProvider $renameLocations)
    {
        $this->renameLocations = $renameLocations;
        
    }

    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        return null; yield;
    }

}