<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse;

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

abstract class AbstractReferenceRenamer implements Renamer
{
    /**
     * @var ReferenceFinder
     */
    private $referenceFinder;

    /**
     * @var TextDocumentLocator
     */
    private $locator;

    /**
     * @var Parser
     */
    private $parser;

    public function __construct(
        ReferenceFinder $referenceFinder,
        TextDocumentLocator $locator,
        Parser $parser
    ) {
        $this->referenceFinder = $referenceFinder;
        $this->locator = $locator;
        $this->parser = $parser;
    }

    abstract protected function getRenameRangeForNode(Node $node): ?ByteOffsetRange;

    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $node = $this->parser->parseSourceFile($textDocument->__toString())->getDescendantNodeAtPosition($offset->toInt());
        return $this->getRenameRangeForNode($node);
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        foreach ($this->referenceFinder->findReferences($textDocument, $offset) as $reference) {
            if (!$reference->isSurely()) {
                continue;
            }

            $textDocument = $this->locator->get($reference->location()->uri());
            $range = $this->getRenameRange($textDocument, $reference->location()->offset());

            if (null === $range) {
                continue;
            }

            yield new LocatedTextEdit(
                $reference->location()->uri(),
                PhpactorTextEdit::create(
                    $range->start(),
                    $range->end()->toInt() - $range->start()->toInt(),
                    $newName
                )
            );
        }
    }

    /**
     * @param Token|Node $tokenOrNode
     */
    protected function offsetRangeFromToken($tokenOrNode, bool $hasDollar): ?ByteOffsetRange
    {
        if (!$tokenOrNode instanceof Token) {
            return null;
        }

        if ($hasDollar) {
            return ByteOffsetRange::fromInts($tokenOrNode->start + 1, $tokenOrNode->getEndPosition());
        }

        return ByteOffsetRange::fromInts($tokenOrNode->start, $tokenOrNode->getEndPosition());
    }
}
