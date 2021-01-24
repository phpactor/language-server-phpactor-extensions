<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerRename\Model\Exception\EmptyNewName;
use Phpactor\Extension\LanguageServerRename\Model\Exception\NoPrearedRenamer;
use Phpactor\Extension\LanguageServerRename\Model\Exception\PreparedForDifferentDocument;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentUri;

class Rename
{
    /**
     * @var Renamer[]
     */
    private $renamers;

    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var ?Renamer
     */
    private $preparedRenamer;
    /**
     * @var ?TextDocumentUri
     */
    private $preparedRenamerUri;
    /**
     * @var ?ByteOffset
     */
    private $preparedRenamerByteOffset;

    /**
     * @param Renamer[] $renamers
     */
    public function __construct(array $renamers, Parser $parser)
    {
        $this->renamers = $renamers;
        $this->parser = $parser;
    }

    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $this->preparedRenamer = null;
        $this->preparedRenamerUri = null;
        $this->preparedRenamerByteOffset = null;

        foreach ($this->renamers as $renamer) {
            if (($range = $renamer->prepareRename($textDocument, $offset)) !== null) {
                $this->preparedRenamer = $renamer;
                $this->preparedRenamerUri = $textDocument->uri();
                $this->preparedRenamerByteOffset = $offset;
                return $range;
            }
        }

        return null;
    }
    
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): \Generator
    {
        if ($this->preparedRenamer === null) {
            throw new NoPrearedRenamer("You need to call prepareRename first.");
        }
        
        if ((string)$this->preparedRenamerUri != (string)$textDocument->uri() || $this->preparedRenamerByteOffset->toInt() != $offset->toInt()) {
            throw new PreparedForDifferentDocument("The operation was prepared for a different document.");
        }

        if (empty($newName)) {
            throw new EmptyNewName();
        }

        return $this->preparedRenamer->rename($textDocument, $offset, $newName);
    }
}
