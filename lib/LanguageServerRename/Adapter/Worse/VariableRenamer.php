<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse;

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
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdits;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

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
        if (($node = $this->getValidNode($textDocument, $offset)) === null) {
            return null;
        }

        [ $token ] = $this->getNodeNameTokens($node);
        $oldName = $this->getTokenNameText($token, (string)$textDocument);

        foreach ($this->renameLocations->provideLocations($textDocument, $offset) as $locationGroup) {
            assert($locationGroup instanceof RenameLocationGroup);
            yield new LocatedTextEdits(
                $this->locationsToTextEdits($locationGroup->uri(), $locationGroup->locations(), $oldName, $newName),
                $locationGroup->uri()
            );
        }
    }
    /** @param Location[] $locations */
    private function locationsToTextEdits(TextDocumentUri $textDocumentUri, array $locations, string $oldName, string $newName): TextEdits
    {
        $textEdits = [];
        $rootNode = $this->parser->parseSourceFile($this->locator->get($textDocumentUri));
        foreach ($locations as $location) {
            $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt());
            $tokens = $this->getNodeNameTokens($node);

            foreach ($tokens as $token) {
                $range = $this->getTokenNameRange($token, $rootNode->getFileContents());
                $rangeText = $this->getTokenNameText($token, $rootNode->getFileContents());

                if ($rangeText == $oldName) {
                    $textEdits[] = TextEdit::create($range->start(), $range->end()->toInt() - $range->start()->toInt(), $newName);
                    break;
                }
            }
        }
        return TextEdits::fromTextEdits($textEdits);
    }

    private function getTokenNameRange(Token $token, string $fileContents): ByteOffsetRange
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

    private function getTokenNameText(Token $token, string $fileContents): string
    {
        $text = (string)$token->getText($fileContents);
        if (mb_substr((string)$token->getText($fileContents), 0, 1) == '$') {
            $text = mb_substr($text, 1);
        }
        return $text;
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
}