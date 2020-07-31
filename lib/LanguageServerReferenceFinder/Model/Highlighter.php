<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Model;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\AssignmentExpression;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\Statement\ExpressionStatement;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\DocumentHighlight;
use Phpactor\LanguageServerProtocol\DocumentHighlightKind;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\WorseReflection\Core\Inference\Variable as PhpactorVariable;

class Highlighter
{
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function highlightsFor(string $source, ByteOffset $offset): Highlights
    {
        $rootNode = $this->parser->parseSourceFile($source);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());

        if ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) {
            return Highlights::fromIterator($this->properties($rootNode, $node->getName()),);
        }

        if ($node instanceof Variable) {
            return Highlights::fromIterator($this->variables($rootNode, $node));
        }

        if ($node instanceof MemberAccessExpression) {
            return Highlights::fromIterator($this->memberAccess($rootNode, $node));
        }

        return Highlights::empty();
    }

    /**
     * @return Generator<DocumentHighlight>
     */
    private function variables(SourceFileNode $rootNode, Variable $node): Generator
    {
        $name = $node->getName();

        foreach ($rootNode->getDescendantNodes() as $node) {
            if ($node instanceof Variable) {
                yield new DocumentHighlight(
                    new Range(
                        PositionConverter::intByteOffsetToPosition($node->getStart(), $node->getFileContents()),
                        PositionConverter::intByteOffsetToPosition($node->getEndPosition(), $node->getFileContents())
                    ),
                    $this->variableKind($node)
                );
            }

            if ($node instanceof Parameter) {
                yield new DocumentHighlight(
                    new Range(
                        PositionConverter::intByteOffsetToPosition($node->variableName->getStartPosition(), $node->getFileContents()),
                        PositionConverter::intByteOffsetToPosition($node->variableName->getEndPosition(), $node->getFileContents()),
                    ),
                    DocumentHighlightKind::READ
                );
            }
        }
    }

    /**
     * @return DocumentHighlightKind::*
     * @phpstan-ignore-next-line 
     */
    private function variableKind(Node $node): int
    {
        $expression = $node->parent;
        if ($expression instanceof AssignmentExpression) {
            if ($expression->leftOperand === $node) {
                return DocumentHighlightKind::WRITE;
            }
        }

        return DocumentHighlightKind::READ;
    }

    /**
     * @return Generator<DocumentHighlight>
     */
    private function properties(Node $rootNode, string $name): Generator
    {
        foreach ($rootNode->getDescendantNodes() as $node) {
            if ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) {
                yield new DocumentHighlight(
                    new Range(
                        PositionConverter::intByteOffsetToPosition($node->getStart(), $node->getFileContents()),
                        PositionConverter::intByteOffsetToPosition($node->getEndPosition(), $node->getFileContents())
                    ),
                    DocumentHighlightKind::TEXT
                );
            }
            if ($node instanceof MemberAccessExpression) {
                if ($name === $node->memberName->getText($rootNode->getFileContents())) {
                    yield new DocumentHighlight(
                        new Range(
                            PositionConverter::intByteOffsetToPosition($node->memberName->getStartPosition(), $node->getFileContents()),
                            PositionConverter::intByteOffsetToPosition($node->memberName->getEndPosition(), $node->getFileContents())
                        ),
                        $this->variableKind($node)
                    );
                }
            }
        }
    }

    /**
     * @return Generator<DocumentHighlight>
     */
    private function memberAccess(SourceFileNode $rootNode, MemberAccessExpression $node): Generator
    {
        if ($node->parent instanceof CallExpression) {
            // method
            return;
        }

        return yield from $this->properties($rootNode, (string)$node->memberName->getText($rootNode->getFileContents()));
    }
}
