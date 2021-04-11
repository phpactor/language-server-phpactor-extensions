<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse\RangeFinder;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdit;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextEdit as PhpactorTextEdit;

class ClassMemberRangeFinder
{
    public function __construct(Parser $parser)
    {
    }
    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $node = $this->parser->parseSourceFile($textDocument->__toString())->getDescendantNodeAtPosition($offset->toInt());

        if ($node instanceof MethodDeclaration) {
            return ByteOffsetRange::fromInts($node->name->start, $node->name->getEndPosition());
        }

        if ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) {
            return $this->offsetRangeFromToken($node->name, true);
        }

        if (
            $node instanceof Variable &&
            (
                $node->getFirstAncestor(ScopedPropertyAccessExpression::class) ||
                $node->getFirstAncestor(MemberAccessExpression::class)
            )
        ) {
            return $this->offsetRangeFromToken($node->name, true);
        }

        if ($node instanceof MemberAccessExpression || $node instanceof ScopedPropertyAccessExpression) {
            return $this->offsetRangeFromToken($node->memberName, false);
        }

        if ($node instanceof ConstElement) {
            return ByteOffsetRange::fromInts($node->name->start, $node->name->getEndPosition());
        }

        return null;
    }
}
