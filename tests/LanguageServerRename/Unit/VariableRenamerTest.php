<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils2;
use Phpactor\Extension\LanguageServerRename\Model\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use function iterator_to_array;

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
            InMemoryDocumentLocator::fromTextDocuments([]),
            new RenameLocationsProvider(
                new class() implements ReferenceFinder {
                    public function findReferences(TextDocument $document, ByteOffset $byteOffset): Generator
                    {
                        return;
                        // @phpstan-ignore-next-line
                        yield;
                    }
                },
                new class() implements DefinitionLocator {
                    public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
                    {
                        return new DefinitionLocation(TextDocumentUri::fromString(""), ByteOffset::fromInt(0));
                    }
                }
            )
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

    /** @dataProvider provideRename */
    public function testRename(string $source): void
    {
        $newName = "newName";
        $offsetExtractor = new OffsetExtractor();
        $offsetExtractor->registerPoint('selection', "<>", function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        $offsetExtractor->registerPoint('definition', "<d>", function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        $offsetExtractor->registerPoint('references', "<r>", function (int $offset) {
            return ByteOffset::fromInt($offset);
        });
        $offsetExtractor->registerRange(
            "resultEdits",
            "{{",
            "}}",
            function (int $start, int $end) use ($newName) {
                return TextEdit::create(
                    ByteOffset::fromInt($start),
                    $end - $start,
                    $newName
                );
            }
        );

        [
            'selection' => [ $selection ],
            'definition' => [ $definition ],
            'references' => $references,
            'resultEdits' => $resultEdits,
            'newSource' => $newSource
        ] = $offsetExtractor->parse($source);

        $textDocumentUri = "file:///test/Class1.php";
        $textDocument = TextDocumentBuilder::create($newSource)
            ->uri($textDocumentUri)
            ->build();

        $renamer = new VariableRenamer(
            new Parser(),
            new NodeUtils2(),
            InMemoryDocumentLocator::fromTextDocuments([
                $textDocumentUri => $textDocument
            ]),
            new RenameLocationsProvider(
                new class($references, $textDocument) implements ReferenceFinder {
                    /** @var array */
                    private $references;
                    /** @var TextDocument */
                    private $textDocument;

                    public function __construct(array $references, TextDocument $textDocument)
                    {
                        $this->references = $references;
                        $this->textDocument = $textDocument;
                    }

                    public function findReferences(TextDocument $document, ByteOffset $byteOffset): Generator
                    {
                        foreach ($this->references as $offset) {
                            yield PotentialLocation::surely(
                                new Location($this->textDocument->uri(), $offset)
                            );
                        }
                    }
                },
                new class($definition, $textDocument) implements DefinitionLocator {
                    /** @var ?ByteOffset */
                    private $definition;
                    /** @var TextDocument */
                    private $textDocument;

                    public function __construct(?ByteOffset $definition, TextDocument $textDocument)
                    {
                        $this->definition = $definition;
                        $this->textDocument = $textDocument;
                    }

                    public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
                    {
                        if ($this->definition !== null) {
                            return new DefinitionLocation(
                                $this->textDocument->uri(),
                                $this->definition
                            );
                        } else {
                            throw new CouldNotLocateDefinition();
                        }
                    }
                }
            )
        );
        $renamer->prepareRename($textDocument, $selection);
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
            // '<?php class Class1 { function method1(){ $var1 = 5; $var2 = $var1 + 5; } }'
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

        yield 'Rename argument' => [
            '<?php class Class1 { function method1(<d>Class2 ${{ar<>g1}}){ <r>${{arg1}} = 5; $var2 = <r>${{arg1}} + 5; } }'
        ];

        yield 'Rename argument (no hint)' => [
            '<?php class Class1 { function method1(<d>${{ar<>g1}}){ <r>${{arg1}} = 5; $var2 = <r>${{arg1}} + 5; } }'
        ];
    }
}
