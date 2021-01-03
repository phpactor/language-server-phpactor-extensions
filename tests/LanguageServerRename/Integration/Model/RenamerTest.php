<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Integration\Model;

use Amp\Promise;
use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\CodeTransform\Domain\Refactor\RenameVariable;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\RenameFile;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentUri;
use Prophecy\Argument;
use Prophecy\Prophet;

class RenamerTest extends TestCase
{
    /**
     * @dataProvider providePrepareRename
     */
    public function testPrepareRename(string $source): void
    {
        $prophet = new Prophet();
        $workspace = $prophet->prophesize(Workspace::class);
        $referenceFinder = $prophet->prophesize(ReferenceFinder::class);
        $definitionLocator = $prophet->prophesize(DefinitionLocator::class);
        $apiClient = new ClientApi(new class implements RpcClient {
            public function notification(string $method, array $params): void
            {
                // empty
            }
            public function request(string $method, array $params): Promise
            {
                return new class implements Promise {
                    // @phpstan-ignore-next-line
                    public function onResolve(callable $onResolved)
                    {
                    }
                };
            }
        });

        list($source, $selectionOffset, , , $ranges) = self::offsetsFromSource($source, null);
        $docItem = new TextDocumentItem("file:///test/testDoc", "php", 1, $source);
        
        $rangesCount = count($ranges);
        if ($rangesCount != 1) {
            throw new \Exception("There must be exactly one expected range in the source, found {$rangesCount}.");
        }
        
        $expectedRange = $ranges[0];

        $renamer = new Renamer(
            $workspace->reveal(), // @phpstan-ignore-line
            new Parser(),
            $referenceFinder->reveal(), // @phpstan-ignore-line
            $definitionLocator->reveal(), // @phpstan-ignore-line
            $apiClient,
            new NodeUtils()
        );

        $actualRange = $renamer->prepareRename(
            $docItem,
            PositionConverter::intByteOffsetToPosition((int)$selectionOffset, $source)
        );
        $this->assertEquals($actualRange, $expectedRange);
    }

    public function providePrepareRename(): Generator
    {
        // {{ .. }} -> the expected rename range
        // <> -> the current cursor position
        yield [
            'Rename class (definition)' =>
            '<?php class {{Clas<>s1}} {}'
        ];

        yield [
            'Rename class (extends)' =>
            '<?php class Class1 extends {{Clas<>s2}} {  }'
        ];

        yield [
            'Rename class (typehint)' =>
            '<?php class Class1 { function f({{Cla<>ss2}} $arg) {} }'
        ];
        
        yield [
            'Rename interface (definition)' =>
            '<?php interface {{Clas<>s1}} {}'
        ];

        yield [
            'Rename interface (implements)' =>
            '<?php class Class1 implements {{Clas<>s2}} {  }'
        ];

        yield [
            'Rename method (definition)' =>
            '<?php class Class1 { function {{meth<>od1}}(){}  }'
        ];

        yield [
            'Rename method (access)' =>
            '<?php class Class1 { function method2(){ $this->{{meth<>od1}}(); } }'
        ];

        yield [
            'Rename static method (access)' =>
            '<?php class Class1 { function method2(){ self::{{meth<>od1}}(); } }'
        ];
        
        yield [
            'Rename method (inside string)' =>
            '<?php class Class1 { function method2(){ $var1 = "a string {$this->{{meth<>od1}}()}"; } }'
        ];
        
        yield [
            'Rename property (definition)' =>
            '<?php class Class1 { public ${{pro<>p1}}; }'
        ];
        
        yield [
            'Rename property (definition)' =>
            '<?php class Class1 { public ${{pro<>p1}}; }'
        ];
        
        yield [
            'Rename property (access)' =>
            '<?php class Class1 { public $prop1; function method1() { $this->{{pr<>op1}} = 2; } }'
        ];
        
        yield [
            'Rename property (access  inside string)' =>
            '<?php class Class1 { public $prop1; function method1() { $var1 = "The value is {$this->{{pr<>op1}}}"; } }'
        ];
        
        yield [
            'Rename const (definition)' =>
            '<?php class Class1 { const {{CONS<>T1}}; } }'
        ];
        
        yield [
            'Rename const (access)' =>
            '<?php class Class1 { public function method1(){ $var1 = Class3::{{CO<>NST14}}; } }'
        ];

        yield [
            'Rename static property (definition)' =>
            '<?php class Class1 { public static ${{st<>aticProp}}; } }'
        ];

        yield [
            'Rename static property (reference)' =>
            '<?php class Class1 { public function method1(){ $var1 = self::${{stati<>cProp}}; } }'
        ];

        yield [
            'Rename argument' =>
            '<?php class Class1 { public function method1(${{a<>rg1}}){ } }'
        ];

        yield [
            'Rename variable' =>
            '<?php class Class1 { public function method1(){ ${{va<>r1}} = 5; } }'
        ];

        yield [
            'Rename variable 2' =>
            '<?php class Class1 { public function method1(){ $${{va<>r1}} = 5; } }'
        ];
    }

    /**
     * @dataProvider provideRename
     */
    public function testRename(string $source, string $uri, string $newName, ?string $renamedUri = null): void
    {
        list(
            $source,
            $selectionOffset,
            $definitionLocation,
            $referenceLocations,
            $ranges
        ) = self::offsetsFromSource($source, $uri);
        
        $refGenerator = function () use ($referenceLocations) {
            foreach ($referenceLocations as $location) {
                yield $location;
            }
        };
        
        $textEdits = array_map(function (Range $range) use ($newName) {
            return new TextEdit($range, $newName);
        }, $ranges);
        
        $changes = [
            new TextDocumentEdit(
                new VersionedTextDocumentIdentifier($uri, 1),
                $textEdits
            ),
        ];

        if (!empty($renamedUri)) {
            $changes[] = new RenameFile("rename", $uri, $renamedUri, null);
        }

        $expectedWorkspaceEdit = new WorkspaceEdit(
            null,
            $changes
        );

        $docItem = new TextDocumentItem($uri, "php", 1, $source);

        $prophet = new Prophet();
        $workspace = $prophet->prophesize(Workspace::class);
        $workspace->has($uri)->willReturn(true);// @phpstan-ignore-line
        $workspace->get($uri)->willReturn(new TextDocumentItem($uri, 'php', 1, $source));// @phpstan-ignore-line
        $referenceFinder = $prophet->prophesize(ReferenceFinder::class);
        // @phpstan-ignore-next-line
        $referenceFinder
            ->findReferences(Argument::any(), Argument::any())
            ->willReturn($refGenerator());
        $definitionLocator = $prophet->prophesize(DefinitionLocator::class);
        // @phpstan-ignore-next-line
        $methodProphecy = $definitionLocator
            ->locateDefinition(Argument::any(), Argument::any());
        if ($definitionLocation !== null) {
            $methodProphecy->willReturn($definitionLocation);
        } else {
            $methodProphecy->willThrow(new CouldNotLocateDefinition());
        }
        
        $apiClient = new ClientApi(new class implements RpcClient {
            public function notification(string $method, array $params): void
            {
                // empty
            }
            public function request(string $method, array $params): Promise
            {
                return new class implements Promise {
                    // @phpstan-ignore-next-line
                    public function onResolve(callable $onResolved)
                    {
                    }
                };
            }
        });

        $renamer = new Renamer(
            $workspace->reveal(), // @phpstan-ignore-line
            new Parser(),
            $referenceFinder->reveal(), // @phpstan-ignore-line
            $definitionLocator->reveal(), // @phpstan-ignore-line
            $apiClient,
            new NodeUtils()
        );

        $actualEdit = $renamer->rename(
            $docItem,
            PositionConverter::intByteOffsetToPosition((int)$selectionOffset, $source),
            "newName"
        );
        
        $this->assertEquals($expectedWorkspaceEdit, $actualEdit);
    }

    public function provideRename(): Generator
    {
        $newName = 'newName';
        $uri = "file:///test/Class1.php";
        $renamedUri = "file:///test/$newName.php";

        // {{ .. }} -> the expected rename range
        // « .. » -> the expected rename range. That symbol is used when {{ }} are adjacent to other braces
        // <d> -> the definition location
        // <r> -> a reference location
        // <> -> the current cursor position
        yield 'Rename class (definition)' => [
            '<?php <d>class {{Clas<>s1}} { function method1(<r>{{Class1}} $arg1){} }',
            $uri,
            $newName,
            $renamedUri
        ];
        
        yield 'Rename class (reference)' => [
            '<?php <d>class {{Class1}} { function method1(<r>{{Cl<>ass1}} $arg1){} }',
            $uri,
            $newName,
            $renamedUri
        ];

        yield 'Rename namespaced class' => [
            '<?php namespace N; class <d>{{NSClass1}} { function method1(<r>{{NSCl<>ass1}} $arg1){} }',
            $uri,
            $newName,
            null
        ];

        yield 'Rename class (with an use statement)' => [
            '<?php '.
            'namespace N1 { <d>class {{Class1}} { function method1(<r>{{Cl<>ass1}} $arg1){ } } } '.
            'namespace N2 { use N1\{{Class1}}; function f(<r>{{Class1}}) { } }',
            $uri,
            $newName,
            $renamedUri
        ];

        yield 'Rename class (with a grouped use statement)' => [
            '<?php '.
            'namespace N\N1 { <d>class «Class1» { function method1(<r>«Cl<>ass1» $arg1){ } } } '.
            'namespace N\N2 { use N\N1\{«Class1», Class2 as C2}; use N\N4\{Class1}; function f(<r>«Class1») { } }',
            $uri,
            $newName,
            $renamedUri
        ];
        
        yield 'Rename class (different from the file name)' => [
            '<?php <d>class {{Class2}} { function method1(<r>{{Cl<>ass2}} $arg1){} }',
            $uri,
            $newName,
        ];

        yield 'Rename imported aliased class' => [
            '<?php '.
            'namespace N2; <d>class Class10 { } '.
            'namespace N; use N2\Class10 as {{C10}}; class Class2 { function method1(<r>{{C<>10}} $arg1){} }',
            $uri,
            $newName,
        ];
        
        yield 'Rename interface (definition)' => [
            '<?php <d>interface {{Clas<>s1}} { }',
            $uri,
            $newName,
            $renamedUri
        ];
        
        yield 'Rename trait (definition)' => [
            '<?php <d>trait {{Clas<>s1}} { }',
            $uri,
            $newName,
            $renamedUri
        ];
        
        yield 'Rename trait (use)' => [
            '<?php <d>trait {{Trait1}} {}; class Class1 { use <r>{{Tra<>it1}}; }',
            $uri,
            $newName,
        ];
        
        yield 'Rename parameter' => [
            '<?php class Class1 { function method1(<d>${{ar<>g1}}){ $var5 = <r>${{arg1}}; } }',
            $uri,
            $newName
        ];
        
        yield 'Rename variable' => [
            '<?php class Class1 { function method1(){ <d>${{va<>r1}} = 5; $var2 = <r>${{var1}} + 5; } }',
            $uri,
            $newName
        ];

        yield 'Rename argument' => [
            '<?php class Class1 { function method1(<d>Class2 ${{ar<>g1}}){ <r>${{arg1}} = 5; $var2 = <r>${{arg1}} + 5; } }',
            $uri,
            $newName
        ];

        yield 'Rename argument (no hint)' => [
            '<?php class Class1 { function method1(<d>${{ar<>g1}}){ <r>${{arg1}} = 5; $var2 = <r>${{arg1}} + 5; } }',
            $uri,
            $newName
        ];

        yield 'Rename property' => [
            '<?php class Class1 { <d>private string ${{prop1}}; function method1(){ $this-><r>{{p<>rop1}} = 5; } }',
            $uri,
            $newName
        ];
    }

    private static function offsetsFromSource(string $source, ?string $uri): array
    {
        $textDocumentUri = $uri !== null ? TextDocumentUri::fromString($uri) : null;
        $results = preg_split("/(<>|<d>|<r>|{{|«|}}|»)/u", $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        $referenceLocations = [];
        $definitionLocation = null;
        $selectionOffset = null;
        $ranges = [];
        $currentResultStartOffset = null;
        if (is_array($results)) {
            $newSource = "";
            $offset = 0;
            foreach ($results as $result) {
                if ($result == "<>") {
                    $selectionOffset = $offset;
                } elseif ($result == "<d>") {
                    $definitionLocation = new DefinitionLocation($textDocumentUri, ByteOffset::fromInt($offset));
                } elseif ($result == "<r>") {
                    $referenceLocations[] = PotentialLocation::surely(
                        new Location($textDocumentUri, ByteOffset::fromInt($offset))
                    );
                } elseif ($result == "{{" || $result == "«") {
                    $currentResultStartOffset = $offset;
                } elseif ($result == "}}" || $result == "»") {
                    $ranges[] =
                        new Range(
                            PositionConverter::byteOffsetToPosition(ByteOffset::fromInt($currentResultStartOffset), $source),
                            PositionConverter::byteOffsetToPosition(ByteOffset::fromInt($offset), $source)
                        );
                } else {
                    $newSource .= $result;
                    $offset += mb_strlen($result);
                }
            }
        } else {
            throw new \Exception('No selection.');
        }
        
        return [$newSource, $selectionOffset, $definitionLocation, $referenceLocations, $ranges];
    }
}
