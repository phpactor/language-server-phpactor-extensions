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
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLanguage;
use Phpactor\TextDocument\TextDocumentUri;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophet;

class RenamerTest extends TestCase
{
	public function testWhat(): void
	{
		$doc = new class implements TextDocument
		{
			public function __toString()
			{
				return '';
			}

			public function uri(): ?TextDocumentUri
			{
				return null;
			}

			public function language(): TextDocumentLanguage
			{
				return TextDocumentLanguage::fromString("php");
			}
		};
		$bo = ByteOffset::fromInt(4);

		// https://github.com/phpspec/prophecy
		$prophecy = $this->prophesize(DefinitionLocator::class);
		// @phpstan-ignore-next-line
		$prophecy->locateDefinition($doc, $bo)->willReturn(new DefinitionLocation(TextDocumentUri::fromString("/test/some/where"), $bo));
		$obj = $prophecy->reveal();
		// @phpstan-ignore-next-line
		dump($obj->locateDefinition($doc, $bo));
		// $renamer = new Renamer(
		// 	null, 
		// 	new Parser(), 
		// 	null, 
		// 	null, 
		// 	null, 
		// 	null, 
		// 	new NodeUtils()
		// );
	}

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
			'<?php class Clas<>s1 {}',
			Range::fromArray([
                'start' => ['line' => 0, 'character' => 12],
                'end' => ['line' => 0, 'character' => 18],
            ])
		];
	}
}