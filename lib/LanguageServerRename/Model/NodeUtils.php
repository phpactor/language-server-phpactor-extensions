<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ClassConstDeclaration;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\Statement\ForeachStatement;
use Microsoft\PhpParser\Node\Statement\InterfaceDeclaration;
use Microsoft\PhpParser\Node\Statement\TraitDeclaration;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;

class NodeUtils
{
    public function getNodeNameText(Node $node, ?string $name = null): ?string
    {
        $token = $this->getNodeNameToken($node, $name);
        if ($token === null) {
            return null;
        }
        $text = (string)$token->getText($node->getFileContents());
        if (mb_substr($text, 0, 1) == '$') {
            return mb_substr($text, 1);
        }
        return $text;
    }
    
    public function getNodeNameStartPosition(Node $node, string $name): ?Position
    {
        $range = $this->getNodeNameRange($node, $name);
        return ($range !== null) ? $range->start : null;
    }

    public function getNodeNameRange(Node $node, ?string $name = null): ?Range
    {
        $token = $this->getNodeNameToken($node, $name);
        if ($token === null) {
            return null;
        }
        
        $fileContents = $node->getFileContents();
        
        $range = $this->getTokenRange($token, $fileContents);
        if (mb_substr((string)$token->getText($fileContents), 0, 1) == '$') {
            $range->start->character++;
        }
        return $range;
    }
    
    public function getTokenRange(Token $token, string $document): Range
    {
        return new Range(
            PositionConverter::intByteOffsetToPosition($token->getStartPosition(), $document),
            PositionConverter::intByteOffsetToPosition($token->getEndPosition(), $document)
        );
    }
    
    public function getNodeNameToken(Node $node, ?string $name = null): ?Token // NOSONAR
    {
        if ($node instanceof ClassDeclaration) {
            return $node->name;
        }
        if ($node instanceof MethodDeclaration) {
            return $node->name;
        }
        
        if ($node instanceof InterfaceDeclaration) {
            return $node->name;
        }
        
        if ($node instanceof TraitDeclaration) {
            return $node->name;
        }
        
        if ($node instanceof QualifiedName && ($nameToken = $node->getLastNamePart()) !== null) {
            return $nameToken;
        }
        
        if ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token) {
            return $node->memberName;
        }
        
        if ($node instanceof Variable && $node->name instanceof Token && $node->getFirstAncestor(PropertyDeclaration::class)) {
            return $node->name;
        }
        
        if ($node instanceof ConstElement) {
            return $node->name;
        }
        
        if ($node instanceof Parameter) {
            return $node->variableName;
        }
        
        if ($node instanceof Variable) {
            while ($node->name instanceof Variable) {
                $node = $node->name;
            }
            if ($node->name instanceof Token) {
                return $node->name;
            }
            return null;
        }
        
        if ($node instanceof MemberAccessExpression) {
            return $node->memberName;
        }
        
        if ($node instanceof ClassConstDeclaration) {
            if (!empty($name)) {
                foreach ($node->constElements->getElements() as $element) {
                    if ($element instanceof ConstElement && $element->getName() == $name) {
                        return $this->getNodeNameToken($element);
                    }
                }
            }
            return null;
        }
        
        if ($node instanceof PropertyDeclaration) {
            if (!empty($name)) {
                foreach ($node->propertyElements->getElements() as $nodeOrToken) {
                    /** @var Node|Token $nodeOrToken */
                    if ($nodeOrToken instanceof Variable
                        && $nodeOrToken->name instanceof Token
                        && $nodeOrToken->getName() == $name
                    ) {
                        return $nodeOrToken->name;
                    }
                }
            }
            return null;
        }

        if ($node instanceof ForeachStatement && !empty($name)) {
            assert($node instanceof ForeachStatement);
            if (
                $node->foreachKey !== null &&
                $node->foreachKey->expression instanceof Variable &&
                $node->foreachKey->expression->name instanceof Token &&
                $node->foreachKey->expression->getName() == $name
            ) {
                return $node->foreachKey->expression->name;
            }
            
            if (
                $node->foreachValue !== null &&
                $node->foreachValue->expression instanceof Variable &&
                $node->foreachValue->expression->name instanceof Token &&
                $node->foreachValue->expression->getName() == $name
            ) {
                return $node->foreachValue->expression->name;
            }
        }
        
        return null;
    }
}
