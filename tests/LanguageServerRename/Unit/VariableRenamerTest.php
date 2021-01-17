<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentBuilder;

class VariableRenamerTest extends TestCase
{
    /** @dataProvider providePrepare */
    public function testPrepare(string $source): void
    {
        $offsetExtractor = new OffsetExtractor();
        $offsetExtractor->registerPoint('selection', "<>", function(int $offset) { return ByteOffset::fromInt($offset); });
        $offsetExtractor->registerRange(
            "expectedRange", 
            "{{", "}}", 
            function (int $start, int $end) {
                return new ByteOffsetRange(
                    ByteOffset::fromInt($start),
                    ByteOffset::fromInt($end),
                );
            }
        );

        [ 
            'selection' => $selection, 
            'expectedRange' => $expectedRange, 
            'newSource' => $newSource 
        ] = $offsetExtractor->parse($source);
        
        $document = TextDocumentBuilder::create($source)
            ->uri("file:///test/testDoc")
            ->build();
        
        $variableRenamer = new VariableRenamer();
        $actualRange = $variableRenamer->prepareRename($document, $selection);
        $this->assertEquals($expectedRange, $actualRange);
    }

    public function providePrepare(): Generator
    {
        yield 'Rename variable' => [
            '<?php ${{va<>r1}} = 5;'
        ];

        // yield 'Rename variable 2' => [
        //     '<?php class Class1 { public function method1(){ $${{va<>r1}} = 5; } }'
        // ];
    }
}
