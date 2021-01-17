<?php

namespace Phpactor\Extension\LanguageServerRename\Model\Renamer;

use Generator;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils2;
use Phpactor\Extension\LanguageServerRename\Model\Renamer2;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;

class VariableRenamer implements Renamer2
{
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var NodeUtils2
     */
    private $nodeUtils;
    /**
     * @var TextDocumentLocator
     */
    private $locator;



    public function __construct(Parser $parser, NodeUtils2 $nodeUtils, TextDocumentLocator $locator)
    {
        $this->parser = $parser;
        $this->nodeUtils = $nodeUtils;
        $this->locator = $locator;
    }

    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $rootNode = $this->parser->parseSourceFile((string)$textDocument);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());
        
        if (
            (
                $node instanceof Variable &&
                null === $node->getFirstAncestor(PropertyDeclaration::class)
            )
            || $node instanceof Parameter
        ) {
            [ $range ] = $this->nodeUtils->getNodeNameRanges($node);
            return $range;
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): ?Generator
    {
        return null;
    }
}
