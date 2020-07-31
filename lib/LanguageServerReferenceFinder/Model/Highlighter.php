<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Model;

use Generator;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\SourceFileNode;
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

        if ($node instanceof Variable) {
            return Highlights::fromIterator($this->variables($rootNode, $node));
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
            if (
                !$node instanceof Variable &&
                !$node instanceof Parameter
            ) {
                continue;
            }
            if ($node->getName() !== $name) {
                continue;
            }

            yield new DocumentHighlight(
                new Range(
                    PositionConverter::intByteOffsetToPosition($node->getStart(), $node->getFileContents()),
                    PositionConverter::intByteOffsetToPosition($node->getEndPosition(), $node->getFileContents())
                ),
                DocumentHighlightKind::READ
            );
        }
    }
}
