<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter;

use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Adapter\RenameLocationGroup;
use Phpactor\Extension\LanguageServerRename\Adapter\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
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
        $offsetExtractor = new OffsetExtractor();
        $offsetExtractor->registerPoint('selection', '<>', function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        $offsetExtractor->registerPoint('definition', '<d>', function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        $offsetExtractor->registerPoint('references', '<r>', function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        
        $selection = null;
        $selectionDocumentUri = null;
        $definition = null;
        $definitionDocumentUri = null;
        $references = [];
        $expected = [];
        foreach ($sources as $uri => $source) {
            [
                'selection' => $selections,
                'definition' => $definitions,
                'references' => $documentReferences,
                'newSource' => $newSource
            ] = $offsetExtractor->parse($source);

            if (!empty($selections)) {
                $selection = $selections[0];
                $selectionDocumentUri = $uri;
            }
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
            new class($references) implements ReferenceFinder {
                /** @var array */
                private $references;

                public function __construct(array $references)
                {
                    $this->references = $references;
                }

                public function findReferences(TextDocument $document, ByteOffset $byteOffset): Generator
                {
                    foreach ($this->references as $uri => $referenceLocations) {
                        foreach ($referenceLocations as $location) {
                            yield PotentialLocation::surely(
                                new Location(
                                    TextDocumentUri::fromString($uri),
                                    $location
                                )
                            );
                        }
                    }
                }
            },
            new class($definition, $definitionDocumentUri) implements DefinitionLocator {
                /** @var ?ByteOffset */
                private $definition;
                /** @var string */
                private $uri;

                public function __construct(?ByteOffset $definition, ?string $uri)
                {
                    $this->definition = $definition;
                    $this->uri = $uri;
                }

                public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
                {
                    if ($this->definition !== null) {
                        return new DefinitionLocation(
                            TextDocumentUri::fromString($this->uri),
                            $this->definition
                        );
                    } else {
                        throw new CouldNotLocateDefinition();
                    }
                }
            }
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
