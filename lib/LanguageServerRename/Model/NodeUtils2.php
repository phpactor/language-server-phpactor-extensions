<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ArrayElement;
use Microsoft\PhpParser\Node\ClassConstDeclaration;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\QualifiedName as MicrosoftQualifiedName;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\Statement\ForeachStatement;
use Microsoft\PhpParser\Node\Statement\InterfaceDeclaration;
use Microsoft\PhpParser\Node\Statement\TraitDeclaration;
use Microsoft\PhpParser\Node\StringLiteral;
use Microsoft\PhpParser\Token;
use Phpactor\TextDocument\ByteOffsetRange;

class NodeUtils2
{
    /** @return string[] */
    public function getNodeNameTexts(Node $node): array
    {
        $tokens = $this->getNodeNameTokens($node);
        $fileContents = $node->getFileContents();
        
        $names = [];
        foreach ($tokens as $token) {
            $text = (string)$token->getText($fileContents);
            if (mb_substr($text, 0, 1) == '$') {
                $text = mb_substr($text, 1);
            }
            $names[] = $text;
        }
        return $names;
    }
    /** @return ByteOffsetRange[] */
    public function getNodeNameRanges(Node $node): array
    {
        $tokens = $this->getNodeNameTokens($node);
        $fileContents = $node->getFileContents();

        $ranges = [];
        foreach ($tokens as $token) {
            $range = $this->getTokenRange($token);
            if (mb_substr((string)$token->getText($fileContents), 0, 1) == '$') {
                $range = ByteOffsetRange::fromInts(
                    $range->start()->toInt() + 1,
                    $range->end()->toInt()
                );
            }
            $ranges[] = $range;
        }
        
        return $ranges;
    }
    
    public function getTokenRange(Token $token): ByteOffsetRange
    {
        return ByteOffsetRange::fromInts(
            $token->getStartPosition(),
            $token->getEndPosition()
        );
    }
    /** @return Token[] */
    public function getNodeNameTokens(Node $node): array // NOSONAR
    {
        if ($node instanceof ClassDeclaration) {
            return [ $node->name ];
        }

        if ($node instanceof InterfaceDeclaration) {
            return [ $node->name ];
        }

        if ($node instanceof TraitDeclaration) {
            return [ $node->name ];
        }

        if ($node instanceof MicrosoftQualifiedName && ($nameToken = $node->getLastNamePart()) !== null) {
            return [ $nameToken ];
        }

        if ($node instanceof MethodDeclaration && $node->name !== null) {
            return [ $node->name ];
        }
        
        if ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token) {
            return [ $node->memberName ];
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

        if ($node instanceof ConstElement) {
            return [ $node->name ];
        }
        
        if ($node instanceof Parameter) {
            return [ $node->variableName ];
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
                }
            }
            return $names;
        }
        
        if ($node instanceof ClassConstDeclaration) {
            $names = [];
            foreach ($node->constElements->getElements() as $element) {
                if ($element instanceof ConstElement) {
                    [$name] = $this->getNodeNameTokens($element);
                    $names[] = $name;
                }
            }
            return $names;
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
