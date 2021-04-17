<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\ReferenceFinder\ClassMover;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\ClassMover\ClassMover;
use Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\ClassMover\ClassRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\MemberRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdit;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdits;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\ReferenceFinder\ReferenceRenamerIntegrationTestCase;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedReferenceFinder;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class ClassRenamerTest extends ReferenceRenamerIntegrationTestCase
{
    /**
     * @dataProvider provideRename
     */
    public function testRename(string $source, string $newName, string $expected): void
    {
        $extractor = OffsetExtractor::create()
            ->registerOffset('offset', '<>')
            ->registerOffset('r', '<r>')
            ->parse($source);

        $offset = $extractor->offset('offset');
        $references = $extractor->offsets('r');

        $source = $extractor->source();
        
        $textDocument = TextDocumentBuilder::create($source)->uri('/foo')->build();
        
        $renamer = $this->createRenamer($references, $textDocument);
        self::assertNotNull($renamer->getRenameRange($textDocument, $offset));

        $actualResults = iterator_to_array($renamer->rename($textDocument, $offset, $newName), false);

        $edits = LocatedTextEditsMap::fromLocatedEdits($actualResults);
        $locateds = $edits->toLocatedTextEdits();
        self::assertCount(1, $locateds);
        $located = reset($locateds);
        assert($located instanceof LocatedTextEdits);
        self::assertEquals($expected, $located->textEdits()->apply($source));

    }

    public function provideRename(): Generator
    {
        yield 'class' => [
            '<?php <r>class Cl<>ass1 { }',
            'Class2',
            '<?php class Class2 { }',
        ];

        yield 'namespaced class' => [
            '<?php namespace Foo; <r>class Cl<>ass1 { }',
            'Class2',
            '<?php namespace Foo; class Class2 { }',
        ];

        yield 'class reference' => [
            '<?php namespace Foo; <r>class Class1 { } <r>Cla<>ss1::foo();',
            'Class2',
            '<?php namespace Foo; class Class2 { } Class2::foo();',
        ];

        yield 'updates namespaced imported class' => [
            '<?php use Foo\Class1; <r>Cla<>ss1::foo();',
            'Class2',
            '<?php use Foo\Class2; Class2::foo();',
        ];

        yield 'updates imported class' => [
            '<?php use Class1; <r>Cla<>ss1::foo();',
            'Class2',
            '<?php use Class2; Class2::foo();',
        ];
    }

    private function createRenamer(array $references, TextDocument $textDocument): ClassRenamer
    {
        return new ClassRenamer(
            $this->offsetsToReferenceFinder($textDocument, $references),
            InMemoryDocumentLocator::fromTextDocuments([$textDocument]),
            new Parser(),
            new ClassMover()
        );
    }
}
