<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\ClassMover;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\ClassMover\ClassMover;
use Phpactor\ClassMover\Domain\Name\QualifiedName;
use Phpactor\Extension\LanguageServerRename\Adapter\Tolerant\TokenUtil;
use Phpactor\Extension\LanguageServerRename\Model\Exception\CouldNotRename;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdit;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextEdit as PhpactorTextEdit;

final class ClassRenamer implements Renamer
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

    /**
     * @var ClassMover
     */
    private $classMover;

    public function __construct(
        ReferenceFinder $referenceFinder,
        TextDocumentLocator $locator,
        Parser $parser,
        ClassMover $classMover
    ) {
        $this->referenceFinder = $referenceFinder;
        $this->locator = $locator;
        $this->parser = $parser;
        $this->classMover = $classMover;
    }

    public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $node = $this->parser->parseSourceFile($textDocument->__toString())->getDescendantNodeAtPosition($offset->toInt());

        if ($node instanceof ClassDeclaration) {
            return TokenUtil::offsetRangeFromToken($node->name);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        $range = $this->getRenameRange($textDocument, $offset);
        $originalName = $this->rangeText($textDocument, $range);

        foreach ($this->referenceFinder->findReferences($textDocument, $offset) as $reference) {
            if (!$reference->isSurely()) {
                continue;
            }

            $referenceDocument = $this->locator->get($reference->location()->uri());

            $edits = $this->classMover->replaceReferences(
                $foundReferences,
                $this->classMover->findReferences($referenceDocument->__toString(), $originalName),
                QualifiedName::fromString($newName)
            );

            foreach ($edits as $edit) {
                yield new LocatedTextEdit(
                    $reference->location()->uri(),
                    $edit
                );
            }
        }
    }

    private function rangeText(TextDocument $textDocument, ByteOffsetRange $range): string
    {
        return substr(
            $textDocument->__toString(),
            $range->start()->toInt(),
            $range->end()->toInt() - $range->start()->toInt()
        );
    }
}
