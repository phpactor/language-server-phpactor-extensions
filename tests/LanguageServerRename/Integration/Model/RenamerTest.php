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
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLanguage;
use Phpactor\TextDocument\TextDocumentUri;
use Prophecy\Argument;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophet;
use function preg_split;

class RenamerTest extends TestCase
{
	// public function testWhat(): void
	// {
	// 	$doc = new class implements TextDocument
	// 	{
	// 		public function __toString()
	// 		{
	// 			return '';
	// 		}

	// 		public function uri(): ?TextDocumentUri
	// 		{
	// 			return null;
	// 		}

	// 		public function language(): TextDocumentLanguage
	// 		{
	// 			return TextDocumentLanguage::fromString("php");
	// 		}
	// 	};
	// 	$bo = ByteOffset::fromInt(4);

	// 	// https://github.com/phpspec/prophecy
	// 	$prophecy = $this->prophesize(DefinitionLocator::class);
	// 	// @phpstan-ignore-next-line
	// 	$prophecy->locateDefinition($doc, $bo)->willReturn(new DefinitionLocation(TextDocumentUri::fromString("/test/some/where"), $bo));
	// 	$obj = $prophecy->reveal();
	// 	// @phpstan-ignore-next-line
	// 	dump($obj->locateDefinition($doc, $bo));
	// 	// $renamer = new Renamer(
	// 	// 	null, 
	// 	// 	new Parser(), 
	// 	// 	null, 
	// 	// 	null, 
	// 	// 	null, 
	// 	// 	null, 
	// 	// 	new NodeUtils()
	// 	// );
	// }

	/**
	 * @dataProvider providePrepareRename
	 */
	public function testPrepareRename(string $source, ?Range $expectedRange): void
	{
		$prophet = new Prophet();
		$workspace = $prophet->prophesize(Workspace::class);
		$referenceFinder = $prophet->prophesize(ReferenceFinder::class);
		$definitionLocator = $prophet->prophesize(DefinitionLocator::class);
		$renameVariable = $prophet->prophesize(RenameVariable::class);
		$apiClient = new ClientApi(new class implements RpcClient {
			public function notification(string $method, array $params): void {}
			public function request(string $method, array $params): Promise {
				return new class implements Promise {
					public function onResolve(callable $onResolved) {} // @phpstan-ignore-line
				};
			}
		});

		[$source, $offset] = ExtractOffset::fromSource($source);
		$docItem = new TextDocumentItem("file:///test/testDoc", "php", 1, $source);
		
		$renamer = new Renamer(
			$workspace->reveal(), // @phpstan-ignore-line
			new Parser(), 
			$referenceFinder->reveal(), // @phpstan-ignore-line
			$definitionLocator->reveal(), // @phpstan-ignore-line
			$apiClient, 
			$renameVariable->reveal(), // @phpstan-ignore-line
			new NodeUtils()
		);

		$actualRange = $renamer->prepareRename(
			$docItem, 
			PositionConverter::intByteOffsetToPosition((int)$offset, $source)
		);
		$this->assertEquals($actualRange, $expectedRange);
	}

	public function providePrepareRename(): Generator
	{
		yield [
			'Rename class (definition)' =>
			'<?php class Clas<>s1 {}',
			Range::fromArray([
                'start' => ['line' => 0, 'character' => 12],
                'end' => ['line' => 0, 'character' => 18],
            ])
		];

		yield [
			'Rename class (extends)' =>
			'<?php class Class1 extends Clas<>s2 {  }',
			Range::fromArray([
                'start' => ['line' => 0, 'character' => 27],
                'end' => ['line' => 0, 'character' => 33],
            ])
		];

		yield [
			'Rename class (implements)' =>
			'<?php class Class1 implements Clas<>s2 {  }',
			Range::fromArray([
                'start' => ['line' => 0, 'character' => 30],
                'end' => ['line' => 0, 'character' => 36],
            ])
		];

		yield [
			'Rename class (typehint)' =>
			'<?php class Class1 { function f(Cla<>ss2 $arg) {} }',
			Range::fromArray([
                'start' => ['line' => 0, 'character' => 32],
                'end' => ['line' => 0, 'character' => 38],
            ])
		];
	}

	/**
	 * @dataProvider provideRename
	 */
	public function testRename(string $source, string $uri, string $newName, ?Range $expectedRange): void
	{
		// $textDocumentUri = TextDocumentUri::fromString($uri);
		// $results = preg_split("/(<>|<d>|<r>)/u", $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
		
		// $referenceLocations = [];
		// $definitionLocation = null;
		// $selectionOffset = null;
		// if(is_array($results)){
		// 	$newSource = "";
		// 	foreach($results as $result){
		// 		if($result[0] == "<>"){
		// 			$selectionOffset = $result[1];
		// 		}else if($result[0] == "<d>"){
		// 			$definitionLocation = PotentialLocation::surely(
		// 				new Location($textDocumentUri, ByteOffset::fromInt($result[1]))
		// 			);
		// 		}else if($result[0] == "<r>"){
		// 			$referenceLocations[] = PotentialLocation::surely(
		// 				new Location($textDocumentUri, ByteOffset::fromInt($result[1]))
		// 			);
		// 		}else{
		// 			$newSource .= $source;
		// 		}
		// 	}
		// } else {
		// 	throw new \Exception('No selection.');
		// }

		list($source, $selectionOffset, $definitionLocation, $referenceLocations) = self::offsetsFromSource($source, $uri);
		
		$refGenerator = function() use($referenceLocations) {
			foreach($referenceLocations as $location)
				yield $location;
		};

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
		if($definitionLocation !== null)
			$methodProphecy->willReturn($definitionLocation);
		else
			$methodProphecy->willThrow(new CouldNotLocateDefinition());
		
		$renameVariable = $prophet->prophesize(RenameVariable::class);
		$apiClient = new ClientApi(new class implements RpcClient {
			public function notification(string $method, array $params): void {}
			public function request(string $method, array $params): Promise {
				return new class implements Promise {
					public function onResolve(callable $onResolved) {} // @phpstan-ignore-line
				};
			}
		});

		$renamer = new Renamer(
			$workspace->reveal(), // @phpstan-ignore-line
			new Parser(), 
			$referenceFinder->reveal(), // @phpstan-ignore-line
			$definitionLocator->reveal(), // @phpstan-ignore-line
			$apiClient, 
			$renameVariable->reveal(), // @phpstan-ignore-line
			new NodeUtils()
		);

		$edit = $renamer->rename(
			$docItem, 
			PositionConverter::intByteOffsetToPosition((int)$selectionOffset, $source),
			"newName"
		);
		// dump($edit);
		// $this->assertEquals($actualRange, $expectedRange);
	}

	public function provideRename(): Generator
	{
		$newName = 'newName';
		$uri = "file:///test/testDoc";

		yield [
			'Rename class (definition)' =>
			'<?php class <d>Clas<>s1 { function method1(<r>Class1 $arg1){} }',
			$uri,
			$newName,
			Range::fromArray([
                'start' => ['line' => 0, 'character' => 12],
                'end' => ['line' => 0, 'character' => 18],
            ])
		];
	}

	private static function offsetsFromSource(string $source, string $uri): array
	{
		$textDocumentUri = TextDocumentUri::fromString($uri);
		$results = preg_split("/(<>|<d>|<r>)/u", $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		
		$referenceLocations = [];
		$definitionLocation = null;
		$selectionOffset = null;
		if(is_array($results)){
			$newSource = "";
			$offset = 0;
			foreach($results as $result){
				if($result == "<>"){
					$selectionOffset = $offset;
				}else if($result == "<d>"){
					$definitionLocation = new DefinitionLocation($textDocumentUri, ByteOffset::fromInt($offset));
				}else if($result == "<r>"){
					$referenceLocations[] = PotentialLocation::surely(
						new Location($textDocumentUri, ByteOffset::fromInt($offset))
					);
				}else{
					$newSource .= $result;
					$offset += mb_strlen($result);
				}
			}
		} else {
			throw new \Exception('No selection.');
		}

		return [$newSource, $selectionOffset, $definitionLocation, $referenceLocations];
	}
}