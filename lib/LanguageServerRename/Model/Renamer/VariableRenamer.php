<?php

namespace Phpactor\Extension\LanguageServerRename\Model\Renamer;

use Generator;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
use Phpactor\Extension\LanguageServerRename\Model\Renamer2;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;

class VariableRenamer implements Renamer2
{
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var NodeUtils
     */
    private $nodeUtils;



    public function __construct(Parser $parser, NodeUtils $nodeUtils)
    {
        $this->parser = $parser;
        $this->nodeUtils = $nodeUtils;
    }

    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $rootNode = $this->parser->parseSourceFile((string)$textDocument);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());

        if (
            (
                $node instanceof Variable &&
                $node->getFirstAncestor(PropertyDeclaration::class) === null
            )
            || $node instanceof Parameter
        ) {
            return $this->nodeUtils->getNodeNameRange($node);
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
