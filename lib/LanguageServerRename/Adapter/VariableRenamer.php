<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ArrayElement;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\ForeachStatement;
use Microsoft\PhpParser\Node\StringLiteral;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;

class VariableRenamer implements Renamer
{
    /**
     * @var RenameLocationsProvider
     */
    private $renameLocations;
    /**
     * @var TextDocumentLocator
     */
    private $locator;
    /**
     * @var Parser
     */
    private $parser;


    public function __construct(RenameLocationsProvider $renameLocations, TextDocumentLocator $locator, Parser $parser)
    {
        $this->renameLocations = $renameLocations;
        $this->locator = $locator;
        $this->parser = $parser;
    }

    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        if (($node = $this->getValidNode($textDocument, $offset)) !== null) {
            [ $token ] = $this->getNodeNameTokens($node);
            return $this->getTokenNameRange($token, (string)$textDocument);
        }
        return null;
    }
    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        return null;
        yield;
    }

    private function getValidNode(TextDocument $textDocument, ByteOffset $offset): ?Node
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
            return $node;
        }
        return null;
    }
    /** 
     * @return Token[] 
     */
    private function getNodeNameTokens(Node $node): array
    {
        if ($node instanceof QualifiedName && $node->getParent() instanceof Parameter) {
            // an argument with a type hint
            return $this->getNodeNameTokens($node->getParent());
        }

        if ($node instanceof Variable) {
            while ($node->name instanceof Variable) {
                $node = $node->name;
            }
            if ($node->name instanceof Token) {
                return [ $node->name ];
            }
            return [];
        }

        if ($node instanceof Parameter) {
            return [ $node->variableName ];
        }

        if ($node instanceof ForeachStatement) {
            assert($node instanceof ForeachStatement);
            $names = [];
            if (
                $node->foreachKey !== null &&
                $node->foreachKey->expression instanceof Variable &&
                $node->foreachKey->expression->name instanceof Token
            ) {
                $names[] = $node->foreachKey->expression->name;
            }
            
            if (
                $node->foreachValue !== null &&
                $node->foreachValue->expression instanceof Variable &&
                $node->foreachValue->expression->name instanceof Token
            ) {
                $names[] = $node->foreachValue->expression->name;
            }
            return $names;
        }
        
        if (
            $node instanceof StringLiteral &&
            $node->getParent() instanceof ArrayElement &&
            $node->getParent()->elementValue instanceof Variable &&
            $node->getParent()->elementValue->name instanceof Token
        ) {
            return [$node->getParent()->elementValue->name ];
        }

        return [];
    }

    public function getTokenNameRange(Token $token, string $fileContents): ByteOffsetRange
    {
        $range = ByteOffsetRange::fromInts(
            $token->getStartPosition(),
            $token->getEndPosition()
        );
        if (mb_substr((string)$token->getText($fileContents), 0, 1) == '$') {
            $range = ByteOffsetRange::fromInts(
                $range->start()->toInt() + 1,
                $range->end()->toInt()
            );
        }
        return $range;
    }
}
