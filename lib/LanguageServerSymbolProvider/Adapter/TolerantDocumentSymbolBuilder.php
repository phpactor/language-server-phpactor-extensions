<?php

namespace Phpactor\Extension\LanguageServerSymbolProvider\Adapter;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerSymbolProvider\Model\DocumentSymbolBuilder;
use Phpactor\LanguageServerProtocol\DocumentSymbol;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\SymbolKind;

class TolerantDocumentSymbolBuilder implements DocumentSymbolBuilder
{
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * {@inheritDoc}
     */
    public function build(string $source): array
    {
        $rootNode = $this->parser->parseSourceFile($source);

        return array_values(array_filter(array_map(function (Node $node) use ($source) {
            return $this->doBuild($node, $source);
        }, iterator_to_array($rootNode->getChildNodes())), function (?DocumentSymbol $symbol) {
            return $symbol !== null;
        }));
    }

    private function doBuild(Node $node, string $source): ?DocumentSymbol
    {
        if ($node instanceof ClassDeclaration) {
            return new DocumentSymbol(
                (string)$node->name->getText($source),
                SymbolKind::CLASS_,
                new Range(
                    PositionConverter::intByteOffsetToPosition($node->getStart(), $source),
                    PositionConverter::intByteOffsetToPosition($node->getEndPosition(), $source)
                ),
                new Range(
                    PositionConverter::intByteOffsetToPosition($node->name->getStartPosition(), $source),
                    PositionConverter::intByteOffsetToPosition($node->name->getEndPosition(), $source)
                )
            );
        }

        return null;
    }
}
