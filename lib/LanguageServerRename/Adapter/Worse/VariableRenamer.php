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
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdit;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class VariableRenamer extends AbstractReferenceRenamer
{
    public function getRenameRangeForNode(Node $node): ?ByteOffsetRange
    {
        if (
            $node instanceof Variable &&
            !$node->getFirstAncestor(PropertyDeclaration::class)
        ) {
            return $this->offsetRangeFromToken($node->name, true);
        }

        if ($node instanceof Parameter) {
            return $this->offsetRangeFromToken($node->variableName, true);
        }

        return null;
    }
}
