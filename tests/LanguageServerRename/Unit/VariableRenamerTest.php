<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils2;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;

class VariableRenamerTest extends TestCase
{
    /** @dataProvider providePrepare */
    public function testPrepare(string $source): void
    {
        $offsetExtractor = new OffsetExtractor();
        $offsetExtractor->registerPoint('selection', "<>", function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        $offsetExtractor->registerRange(
            "expectedRange",
            "{{",
            "}}",
            function (int $start, int $end) {
                return new ByteOffsetRange(
                    ByteOffset::fromInt($start),
                    ByteOffset::fromInt($end),
                );
            }
        );

        [
            'selection' => [ $selection ],
            'expectedRange' => $expectedRanges,
            'newSource' => $newSource
        ] = $offsetExtractor->parse($source);
        
        $expectedRange = count($expectedRanges) > 0 ? $expectedRanges[0] : null;

        $document = TextDocumentBuilder::create($newSource)
            ->uri("file:///test/testDoc")
            ->build();
        
        $variableRenamer = new VariableRenamer(
            new Parser(), 
            new NodeUtils2(), 
            InMemoryDocumentLocator::fromTextDocuments([])
        );
        $actualRange = $variableRenamer->prepareRename($document, $selection);
        $this->assertEquals($expectedRange, $actualRange);
    }

    public function providePrepare(): Generator
    {
        yield [
            'Rename argument' =>
            '<?php class Class1 { public function method1(${{a<>rg1}}){ } }'
        ];

        yield 'Rename variable' => [
            '<?php ${{va<>r1}} = 5;'
        ];

        yield [
            'Rename dynamic variable' =>
            '<?php class Class1 { public function method1(){ $${{va<>r1}} = 5; } }'
        ];

        yield [
            'Rename variable in list deconstruction' =>
            '<?php class Class1 { public function method1(){ [ ${{va<>r1}} ] = someFunc(); } }'
        ];

        yield [
            'NULL: Rename static property (definition)' =>
            '<?php class Class1 { public static $st<>aticProp; } }'
        ];

        yield [
            'NULL: Rename property (definition)' =>
            '<?php class Class1 { public $pro<>p; } }'
        ];

        yield [
            'NULL: Rename property (multiple definition)' =>
            '<?php class Class1 { public $prop1, $pr<>op2; } }'
        ];
    }
}
