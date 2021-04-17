<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\ClassMover;

use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\CodeTransform\Domain\Generator;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\MemberRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdits;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedReferenceFinder;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class ClassRenamerTest extends TestCase
{
    /**
     * @dataProvider provideRename
     */
    public function testRename(string $source, string $newName): void
    {
        $extractor = OffsetExtractor::create()
            ->registerOffset('s', '<>')
            ->registerOffset('r', '<r>')
            ->registerRange('renameRange', '{{', '}}')
            ->parse($source);

        $selection = $extractor->offset('s');
        $references = $extractor->offsets('r');
        $resultEditRanges = $extractor->ranges('renameRange');
        $newSource = $extractor->source();
        
        $textDocument = TextDocumentBuilder::create($newSource)
            ->uri(self::EXAMPLE_DOCUMENT_URI)
            ->build();
        
        $renamer = new ClassRenamer(
            new PredefinedReferenceFinder(...array_map(function (ByteOffset $reference) use ($textDocument) {
                return PotentialLocation::surely(new Location($textDocument->uri(), $reference));
            }, $references)),
            InMemoryDocumentLocator::fromTextDocuments([$textDocument]),
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
            LocatedTextEditsMap::fromLocatedEdits($actualResults)->toLocatedTextEdits()
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
        yield 'property and definition' => [
            '<?php class Foobar { <r>private ${{foobar}}; function bar() { return $this->{{<r>fo<>obar}}; } }'
        ];
        yield 'constant and definition' => [
            '<?php class Foobar { <r>const {{FOO}}="bar"; function bar() { return self::{{<r>F<>OO}}; } }'
        ];
    }
}
