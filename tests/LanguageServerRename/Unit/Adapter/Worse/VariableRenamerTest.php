<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\Worse;

use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Generator;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedDefinitionLocator;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class VariableRenamerTest extends TestCase
{
    /** @dataProvider provideGetRenameRange */
    public function testGetRenameRange(string $source): void
    {
        $extractor = OffsetExtractor::create()
            ->registerOffset('selection', '<>')
            ->registerRange('expectedRange', '{{', '}}')
            ->parse($source);

        [ $selection ] = $extractor->offsets('selection');
        $expectedRanges = $extractor->ranges('expectedRange');
        $newSource = $extractor->source();
        
        $expectedRange = count($expectedRanges) > 0 ? $expectedRanges[0] : null;

        $document = TextDocumentBuilder::create($newSource)
            ->uri('file:///test/testDoc')
            ->build();
        
        $variableRenamer = new VariableRenamer(
            new RenameLocationsProvider(
                new PredefinedReferenceFinder([]),
                new PredefinedDefinitionLocator(ByteOffset::fromInt(0), '')
            ),
            InMemoryDocumentLocator::fromTextDocuments([]),
            new Parser()
        );
        $actualRange = $variableRenamer->getRenameRange($document, $selection);
        $this->assertEquals($expectedRange, $actualRange);
    }

    public function provideGetRenameRange(): Generator
    {
        yield 'Rename argument' => [
            '<?php class Class1 { public function method1(${{a<>rg1}}){ } }'
        ];

        yield 'Rename variable' => [
            '<?php ${{va<>r1}} = 5;'
        ];

        yield 'Rename dynamic variable' => [
            '<?php class Class1 { public function method1(){ $${{va<>r1}} = 5; } }'
        ];

        yield 'Rename variable in list deconstruction' => [
            '<?php class Class1 { public function method1(){ [ ${{va<>r1}} ] = someFunc(); } }'
        ];

        yield 'NULL: Rename static property (definition)' => [
            '<?php class Class1 { public static $st<>aticProp; } }'
        ];

        yield 'NULL: Rename property (definition)' => [
            '<?php class Class1 { public $pro<>p; } }'
        ];

        yield 'NULL: Rename property (multiple definition)' => [
            '<?php class Class1 { public $prop1, $pr<>op2; } }'
        ];
    }
    /** @dataProvider provideRename */
    public function testRename(string $source): void
    {
        $extractor = OffsetExtractor::create()
            ->registerOffset('selection', '<>')
            ->registerOffset('definition', '<d>')
            ->registerOffset('references', '<r>')
            ->registerRange('resultEditRanges', '{{', '}}')
            ->parse($source);

        [ $selection ] = $extractor->offsets('selection');
        [ $definition ] = $extractor->offsets('definition');
        $references = $extractor->offsets('references');
        $resultEditRanges = $extractor->ranges('resultEditRanges');
        $newSource = $extractor->source();
        
        $newName = 'newName';

        $textDocumentUri = 'file:///test/Class1.php';
        $textDocument = TextDocumentBuilder::create($newSource)
            ->uri($textDocumentUri)
            ->build();
        
        $renamer = new VariableRenamer(
            new RenameLocationsProvider(
                new PredefinedReferenceFinder([$textDocumentUri => $references]),
                new PredefinedDefinitionLocator($definition, $textDocumentUri)
            ),
            InMemoryDocumentLocator::fromTextDocuments([
                $textDocumentUri => $textDocument
            ]),
            new Parser(),
        );

        $resultEdits = [];
        foreach ($resultEditRanges as $range) {
            assert($range instanceof ByteOffsetRange);
            $resultEdits[] = TextEdit::create(
                $range->start(),
                $range->end()->toInt() - $range->start()->toInt(),
                $newName
            );
        }

        $renamer->getRenameRange($textDocument, $selection);
        $actualResults = iterator_to_array($renamer->rename($textDocument, $selection, $newName), false);
        $this->assertEquals(
            [
                new RenameResult(
                    TextEdits::fromTextEdits($resultEdits),
                    $textDocument->uri()
                )
            ],
            $actualResults
        );
    }

    public function provideRename(): Generator
    {
        yield 'Rename variable' => [
            '<?php class Class1 { function method1(){ <d>${{va<>r1}} = 5; $var2 = <r>${{var1}} + 5; } }'
        ];

        yield 'Rename parameter' => [
            '<?php class Class1 { function method1(<d>${{ar<>g1}}){ $var5 = <r>${{arg1}}; } }'
        ];

        yield 'Rename variable (in list deconstructor)' => [
            '<?php class Class1 { function method1(){ [ <d>${{va<>r1}} ] = 5; $var2 = <r>${{var1}} + 5; } }'
        ];

        yield 'Rename variable (in list deconstructor with key)' => [
            '<?php class Class1 { function method1(){ [ <d>"key"=>${{va<>r1}} ] = 5; $var2 = <r>${{var1}} + 5; } }'
        ];

        yield 'Rename variable (in list function no key)' => [
            '<?php class Class1 { function method1(){ list(<d>${{va<>r1}}) = 5; $var2 = <r>${{var1}} + 5; } }'
        ];

        yield 'Rename variable (in list function with key)' => [
            '<?php class Class1 { function method1(){ list(<d>"key"=>${{va<>r1}}) = 5; $var2 = <r>${{var1}} + 5; } }'
        ];

        yield 'Rename variable (as foreach array)' => [
            '<?php class Class1 { function method1(){ <d>${{var}} = []; foreach(<r>${{v<>ar}} as $val) { } } }'
        ];

        yield 'Rename variable (as foreach value)' => [
            '<?php class Class1 { function method1(){ $var = []; <d>foreach($var as ${{val}}) { <r>${{v<>al}} += 5; } } }'
        ];

        yield 'Rename variable (as foreach key)' => [
            '<?php class Class1 { function method1(){ $var = []; <d>foreach($var as ${{key}}=>$val) { <r>${{k<>ey}} += 5; } } }'
        ];

        yield 'Rename argument' => [
            '<?php class Class1 { function method1(<d>Class2 ${{ar<>g1}}){ <r>${{arg1}} = 5; $var2 = <r>${{arg1}} + 5; } }'
        ];

        yield 'Rename argument (no hint)' => [
            '<?php class Class1 { function method1(<d>${{ar<>g1}}){ <r>${{arg1}} = 5; $var2 = <r>${{arg1}} + 5; } }'
        ];

        yield 'Rename foreach variable' => [
            '<?php $var1 = 0; <d>foreach($array as ${{value}}) { echo <r>${{val<>ue}}; }'
        ];
    }
}
