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
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\TextDocument\TextDocument;

class NodeUtils
{
	
    public function getNodeNameText(Node $node, TextDocument $phpactorDocument): ?string
    {
        $token = $this->getNodeNameToken($node);
        if($token === null)
            return null;
        return (string)$token->getText((string)$phpactorDocument);
	}
	
    public function getNodeNameStartPosition(Node $node, string $name): ?Position
    {
        $range = $this->getNodeNameRange($node, $name);
        return ($range !== null) ? $range->start : null;
    }

    public function getNodeNameRange(Node $node, ?string $name = null): ?Range
    {
        $token = $this->getNodeNameToken($node, $name);
        if($token === null)
            return null;
        
        $fileContents = $node->getRoot()->fileContents;
        
        $range = $this->getTokenRange($token, $fileContents);
        if(mb_substr((string)$token->getText($fileContents), 0, 1) == '$')
            $range->start->character++;
        return $range;
	}
	
    public function getTokenRange(Token $token, string $document): Range
    {
        return new Range(
            PositionConverter::intByteOffsetToPosition($token->getStartPosition(), $document),
            PositionConverter::intByteOffsetToPosition($token->getEndPosition(), $document)
        );
    }

    public function getNodeNameToken(Node $node, ?string $name = null): ?Token
    {
        
        if ($node instanceof MethodDeclaration) {
            return $node->name;
        } elseif ($node instanceof ClassDeclaration) {
            return $node->name;
        } elseif ($node instanceof QualifiedName && ($nameToken = $node->getLastNamePart()) !== null) {
            return $nameToken;
        } elseif ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token) {
            return $node->memberName;
        } elseif ($node instanceof Variable && $node->name instanceof Token && $node->getFirstAncestor(PropertyDeclaration::class)) {
            return $node->name;
        } elseif ($node instanceof ConstElement) {
            return $node->name;
        } elseif ($node instanceof ClassConstDeclaration) {
            foreach($node->constElements->getElements() as $element){
                if ($element instanceof ConstElement && !empty($name) && $element->getName() == $name) {
                    return $this->getNodeNameToken($element);
                }
            }
            return null;
        } elseif ($node instanceof Parameter) {
            return $node->variableName;
        } elseif ($node instanceof Variable) {
            while($node->name instanceof Variable)
                $node = $node->name;
            if($node->name instanceof Token)
                return $node->name;
            return null;
        } elseif ($node instanceof MemberAccessExpression) {
            return $node->memberName;
        } elseif ($node instanceof PropertyDeclaration) {
            foreach ($node->propertyElements->getElements() as $nodeOrToken) {
                /** @var Node|Token $nodeOrToken */
                if ($nodeOrToken instanceof Variable
                    && $nodeOrToken->name instanceof Token && 
                    !empty($name) 
                    && $nodeOrToken->getName() == $name
                ) {
                    return $nodeOrToken->name;
                }
            }
            return null;
        } 
        
        return null;
    }
}
