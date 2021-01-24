<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
use Phpactor\Extension\LanguageServerRename\Model\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\MemberRenamer;
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
use Phpactor\TextDocument\TextEdits;
use Phpactor\TextDocument\TextEdit;

class MemberRenamerTest extends TestCase
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
        
        $variableRenamer = new MemberRenamer(
            new Parser(),
            new NodeUtils(),
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
        yield 'Rename method (definition)' => [
            '<?php class Class1 { function {{meth<>od1}}(){}  }'
        ];

        yield 'Rename method (access)' => [
            '<?php class Class1 { function method2(){ $this->{{meth<>od1}}(); } }'
        ];

        yield 'Rename static method (access)' => [
            '<?php class Class1 { function method2(){ self::{{meth<>od1}}(); } }'
        ];
        
        yield 'Rename method (inside string)' => [
            '<?php class Class1 { function method2(){ $var1 = "a string {$this->{{meth<>od1}}()}"; } }'
        ];
        
        yield 'Rename property (definition)' => [
            '<?php class Class1 { public ${{pro<>p1}}; }'
        ];
        
        yield 'Rename property (access)' => [
            '<?php class Class1 { public $prop1; function method1() { $this->{{pr<>op1}} = 2; } }'
        ];
        
        yield 'Rename property (access  inside string)' => [
            '<?php class Class1 { public $prop1; function method1() { $var1 = "The value is {$this->{{pr<>op1}}}"; } }'
        ];
        
        yield 'Rename const (definition)' => [
            '<?php class Class1 { const {{CONS<>T1}}; } }'
        ];
        
        yield 'Rename const (access)' => [
            '<?php class Class1 { public function method1(){ $var1 = Class3::{{CO<>NST14}}; } }'
        ];

        yield 'Rename static property (definition)' => [
            '<?php class Class1 { public static ${{st<>aticProp}}; } }'
        ];

        yield 'Rename static property (reference)' => [
            '<?php class Class1 { public function method1(){ $var1 = self::${{stati<>cProp}}; } }'
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

        $renamer = new MemberRenamer(
            new Parser(),
            new NodeUtils(),
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
        yield 'Rename property' => [
            '<?php class Class1 { <d>private string ${{prop1}}; function method1(){ $this-><r>{{p<>rop1}} = 5; } }'
        ];

        yield 'Rename property (multiple declaration)' => [
            '<?php class Class1 { <d>private string $prop2 = "2", ${{prop1}} = "3"; function method1(){ $this-><r>{{p<>rop1}} = 5; } }'
        ];

        yield 'Rename property (access  inside string)' => [
            '<?php class Class1 { <d>public ${{prop1}}; function method1() { $var1 = "The value is {$this-><r>{{pr<>op1}}}"; } }'
        ];

        yield 'Rename const' => [
            '<?php class Class1 { <d>const {{CONST1}}; function method1(){ self::<r>{{CONS<>T1}} = 5; } }'
        ];

        yield 'Rename const (multiple declaration without initialisation)' => [
            '<?php class Class1 { <d>const {{CONST1}}, CONST2; function method1(){ self::<r>{{CONS<>T1}} = 5; } }'
        ];

        yield 'Rename method' => [
            '<?php class Class1 { <d>function {{method1}}() { } function method2(){ $var1 = $this-><r>{{meth<>od1}}(); } }'
        ];

        yield 'Rename static method' => [
            '<?php class Class1 { <d>static function {{method1}}() { } function method2(){ $var1 = self::<r>{{meth<>od1}}(); } }'
        ];
    }
}
