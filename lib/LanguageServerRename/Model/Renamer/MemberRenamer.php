<?php

namespace Phpactor\Extension\LanguageServerRename\Model\Renamer;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ClassConstDeclaration;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\AssignmentExpression;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
use Phpactor\Extension\LanguageServerRename\Model\RenameLocationGroup;
use Phpactor\Extension\LanguageServerRename\Model\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class MemberRenamer implements Renamer
{
    /** @var Parser */
    private $parser;
    /** @var NodeUtils */
    private $nodeUtils;
    /** @var TextDocumentLocator */
    private $locator;
    /** @var RenameLocationsProvider */
    private $locationsProvider;

    public function __construct(
        Parser $parser,
        NodeUtils $nodeUtils,
        TextDocumentLocator $locator,
        RenameLocationsProvider $locationsProvider
    ) {
        $this->parser = $parser;
        $this->nodeUtils = $nodeUtils;
        $this->locator = $locator;
        $this->locationsProvider = $locationsProvider;
    }

    private function getValidNode(TextDocument $textDocument, ByteOffset $offset): ?Node
    {
        $rootNode = $this->parser->parseSourceFile((string)$textDocument);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());
        
        if (
            $node instanceof MethodDeclaration
            || ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token)
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class))
            || ($node instanceof Variable && $node->getFirstAncestor(ScopedPropertyAccessExpression::class))
            || $node instanceof ConstElement
            || $node instanceof ClassConstDeclaration
            || ($node instanceof MemberAccessExpression && $node->memberName instanceof Token)
        ) {
            return $node;
        }
        
        return null;
    }

    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        if (($node = $this->getValidNode($textDocument, $offset)) !== null) {
            [ $token ] = $this->getNodeNameTokens($node);
            return $this->nodeUtils->getTokenNameRange($token, (string)$textDocument);
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
        $oldName = $this->nodeUtils->getTokenNameText($token, (string)$textDocument);

        foreach ($this->locationsProvider->provideLocations($textDocument, $offset) as $locationGroup) {
            /** @var RenameLocationGroup $locationGroup */
            yield new RenameResult(
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
                $range = $this->nodeUtils->getTokenNameRange($token, $rootNode->getFileContents());
                $rangeText = $this->nodeUtils->getTokenNameText($token, $rootNode->getFileContents());

                if ($rangeText == $oldName) {
                    $textEdits[] = TextEdit::create($range->start(), $range->end()->toInt() - $range->start()->toInt(), $newName);
                    break;
                }
            }
        }
        return TextEdits::fromTextEdits($textEdits);
    }
    /** @return Token[] */
    public function getNodeNameTokens(Node $node): array
    {
        if ($node instanceof MethodDeclaration && $node->name !== null) {
            return [ $node->name ];
        }

        if ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token) {
            return [ $node->memberName ];
        }
        
        if ($node instanceof Variable && $node->name instanceof Token) {
            return [ $node->name ];
        }

        if ($node instanceof ConstElement) {
            return [ $node->name ];
        }

        if ($node instanceof MemberAccessExpression) {
            return [ $node->memberName ];
        }
        
        if ($node instanceof PropertyDeclaration) {
            $names = [];
            foreach ($node->propertyElements->getElements() as $nodeOrToken) {
                /** @var Node|Token $nodeOrToken */
                if ($nodeOrToken instanceof Variable
                    && $nodeOrToken->name instanceof Token
                ) {
                    $names[] = $nodeOrToken->name;
                    continue;
                }
                
                if (
                    $nodeOrToken instanceof AssignmentExpression
                    && $nodeOrToken->leftOperand instanceof Variable
                    && $nodeOrToken->leftOperand->name instanceof Token
                ) {
                    $names[] = $nodeOrToken->leftOperand->name;
                }
            }
            return $names;
        }
        
        if ($node instanceof ClassConstDeclaration) {
            $names = [];
            foreach ($node->constElements->getElements() as $element) {
                if ($element instanceof ConstElement) {
                    [ $name ] = $this->getNodeNameTokens($element);
                    $names[] = $name;
                }
            }
            return $names;
        }
        
        return [];
    }
}
