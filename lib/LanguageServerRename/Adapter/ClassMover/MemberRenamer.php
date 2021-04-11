<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\ClassMover;

use Generator;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\ClassMover\Domain\MemberFinder;
use Phpactor\ClassMover\Domain\MemberReplacer;
use Phpactor\ClassMover\Domain\Model\ClassMemberQuery;
use Phpactor\ClassMover\Domain\Reference\MemberReference;
use Phpactor\ClassMover\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextEdit as PhpactorTextEdit;

class MemberRenamer implements Renamer
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
    )
    {
        $this->referenceFinder = $referenceFinder;
        $this->locator = $locator;
        $this->parser = $parser;
    }

    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $node = $this->parser->parseSourceFile($textDocument->__toString())->getDescendantNodeAtPosition($offset->toInt());

        if ($node instanceof MethodDeclaration) {
            return ByteOffsetRange::fromInts($node->name->start, $node->name->getEndPosition());
        }
        if (!$node instanceof MemberAccessExpression && !$node instanceof ScopedPropertyAccessExpression) {
            return null;
        }

        $memberName = $node->memberName;

        if (!$memberName instanceof Token) {
            return null;
        }

        return ByteOffsetRange::fromInts($memberName->start, $memberName->getEndPosition());
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        $edits = LocatedTextEditsMap::create();

        foreach ($this->referenceFinder->findReferences($textDocument, $offset) as $reference) {
            if (!$reference->isSurely()) {
                continue;
            }
            $textDocument = $this->locator->get($reference->location()->uri());
            $range = $this->getRenameRange($textDocument, $reference->location()->offset());

            $edits = $edits->withTextEdit(
                $reference->location()->uri(),
                PhpactorTextEdit::create(
                    $range->start(),
                    $range->end()->toInt() - $range->start()->toInt(),
                    $newName
                )
            );
        }

        yield from $edits->toLocatedTextEdits();
    }
}
