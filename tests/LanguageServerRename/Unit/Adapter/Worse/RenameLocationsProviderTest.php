<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\Worse;

use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\RenameLocationGroup;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedDefinitionLocator;
use Phpactor\Extension\LanguageServerRename\Tests\Unit\PredefinedReferenceFinder;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentUri;

class RenameLocationsProviderTest extends TestCase
{
    /**
     * @param array<string,string> $sources
     * @dataProvider provideLocations
     */
    public function testLocations(array $sources): void
    {
        $offsetExtractor = OffsetExtractor::create()
            ->registerOffset('selection', '<>')
            ->registerOffset('definition', '<d>')
            ->registerOffset('references', '<r>');
        
        $selection = null;
        $selectionDocumentUri = null;
        $definition = null;
        $definitionDocumentUri = null;
        $references = [];
        $expected = [];
        foreach ($sources as $uri => $source) {
            $offsets = $offsetExtractor->parse($source);

            $selections = $offsets->offsets('selection');
            $definitions = $offsets->offsets('definition');
            $documentReferences = $offsets->offsets('references');
            $newSource = $offsets->source();
            
            if (!empty($selections)) {
                $selection = $selections[0];
                $selectionDocumentUri = $uri;
            }
            $textDocument =
                TextDocumentBuilder::create($source)
                ->uri($uri)
                ->build();
            $textDocumentUri = TextDocumentUri::fromString($uri);
            $locations = [];
            if (!empty($definitions)) {
                $definition = $definitions[0];
                $definitionDocumentUri = $uri;
                $locations[] = new DefinitionLocation($textDocumentUri, $definition);
            }
            $references[$uri] = $documentReferences;

            $expected[] = new RenameLocationGroup(
                TextDocumentUri::fromString($uri),
                array_merge($locations, array_map(function (ByteOffset $offset) use ($textDocumentUri) {
                    return new Location($textDocumentUri, $offset);
                }, $documentReferences))
            );
        }
        
        $provider = new RenameLocationsProvider(
            new PredefinedReferenceFinder($references),
            new PredefinedDefinitionLocator($definition, $definitionDocumentUri)
        );

        $actualResults = iterator_to_array(
            $provider->provideLocations(
                TextDocumentBuilder::create('')
                    ->uri($selectionDocumentUri)
                    ->build(),
                $selection
            ),
            false
        );
        
        $this->assertEquals(
            $expected,
            $actualResults
        );
    }
    
    public function provideLocations(): Generator
    {
        yield 'Locations with only definition in a single file' => [
            [
                'file:///test/Class1.php' => '<?php class Class1 { function <d>metho<>d1(){ } }',
            ]
        ];

        yield 'Locations with definition in a single file' => [
            [
                'file:///test/Class1.php' => '<?php class Class1 { function <d>metho<>d1(){ } function method2() { $this-><r>method1(); $this-><r>method1(); } }',
            ]
        ];

        yield 'Locations with no definition in a single file' => [
            [
                'file:///test/Class1.php' => '<?php class Class1 { function method1(){ $this-><r>meth<>od1(); } function method2() { $this-><r>meth<>od1(); } }',
            ]
        ];

        yield 'Locations with definition in two files' => [
            [
                'file:///test/Class1.php' => '<?php class Class1 { function <d>metho<>d1(){ $this-><r>method1(); } function method2() { $this-><r>method1(); } }',
                'file:///test/Class2.php' => '<?php class Class2 { function method3(){ $class1 = new Class1(); $class1-><r>method1(); } }'
            ]
        ];

        yield 'Locations with definition in one file and references in another' => [
            [
                'file:///test/Class2.php' => '<?php class Class2 { function method3(){ $class1 = new Class1(); $class1-><r>method1(); $class1-><r>method1(); } }',
                'file:///test/Class1.php' => '<?php class Class1 { function <d>metho<>d1(){ } }',
            ]
        ];
    }
}
