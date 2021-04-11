<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Phpactor\TextDocument\ByteOffsetRange;

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
