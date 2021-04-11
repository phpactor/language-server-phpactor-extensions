<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\ClassMover;

use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Generator;
use Phpactor\ClassMover\Domain\MemberFinder;
use Phpactor\Extension\LanguageServerRename\Adapter\ClassMover\MemberRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdits;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedDefinitionLocator;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedReferenceFinder;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class MemberRenamerTest extends TestCase
{
    const EXAMPLE_DOCUMENT_URI = 'file:///test/Class1.php';

    /** 
     * @dataProvider provideGetRenameRange 
     */
    public function testGetRenameRange(string $source): void
    {
        $extractor = OffsetExtractor::create()
            ->registerOffset('selection', '<>')
            ->registerRange('expectedRange', '{{', '}}')
            ->parse($source);
        
        $selection = $extractor->offset('selection');
        $expectedRanges = $extractor->ranges('expectedRange');
        $newSource = $extractor->source();
        
        $expectedRange = count($expectedRanges) > 0 ? $expectedRanges[0] : null;

        $document = TextDocumentBuilder::create($newSource)
            ->uri('file:///test/testDoc')
            ->build();
        
        $variableRenamer = new MemberRenamer(
            new PredefinedReferenceFinder([]),
            InMemoryDocumentLocator::fromTextDocuments([]),
            new Parser()
        );

        $actualRange = $variableRenamer->getRenameRange($document, $selection);

        $this->assertEquals($expectedRange, $actualRange);
    }

    public function provideGetRenameRange(): Generator
    {
        yield 'method declaration' => [
            '<?php class Class1 { public function {{me<>thod1}}(){ } }'
        ];
        yield 'method call' => [
            '<?php $foo->{{me<>thod1}}(); }'
        ];
        yield 'static method call' => [
            '<?php Foobar::{{me<>thod1}}(); }'
        ];
        yield 'property declaration' => [
            '<?php class Class1 { public ${{prop<>erty}}; }'
        ];
        yield 'property access' => [
            '<?php $foo->${{me<>thod1}};'
        ];
        yield 'static property access' => [
            '<?php Foobar::${{me<>thod1}}; }'
        ];
        yield 'constant declaration' => [
            '<?php class Class1 { const {{F<>OO}}="bar"; }'
        ];
        yield 'constant access' => [
            '<?php Foo::{{F<>OO}};'
        ];
    }

    /** 
     * @dataProvider provideRename 
     */
    public function testRename(string $source): void
    {
        $extractor = OffsetExtractor::create()
            ->registerOffset('selection', '<>')
            ->registerOffset('references', '<r>')
            ->registerRange('resultEditRanges', '{{', '}}')
            ->parse($source);

        $selection = $extractor->offset('selection');
        $references = $extractor->offsets('references');
        $resultEditRanges = $extractor->ranges('resultEditRanges');
        $newSource = $extractor->source();
        
        $newName = 'newName';

        $textDocumentUri = self::EXAMPLE_DOCUMENT_URI;
        $textDocument = TextDocumentBuilder::create($newSource)
            ->uri($textDocumentUri)
            ->build();
        
        $renamer = new MemberRenamer(
            new PredefinedReferenceFinder([$textDocumentUri => $references]),
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
                new LocatedTextEdits(
                    TextEdits::fromTextEdits($resultEdits),
                    $textDocument->uri()
                )
            ],
            $actualResults
        );
    }

    public function provideRename(): Generator
    {
        yield 'method declaration' => [
            '<?php class Class1 { function {{<r>meth<>od1}}() { } }'
        ];
        yield 'method call' => [
            '<?php $foo->{{<r>me<>thod1}}(); }'
        ];
        yield 'method calls' => [
            '<?php $foo->{{<r>me<>thod1}}(); $foo->{{<r>me<>thod1}}();}'
        ];
        yield 'static method call' => [
            '<?php Foobar::{{<r>me<>thod1}}(); }'
        ];
    }
}
