<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Tests\Unit\Handler;

use Phpactor\LanguageServerProtocol\Location as LspLocation;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\ReferenceContext;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\ReferencesHandler;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServer\Test\HandlerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentBuilder;

class ReferencesHandlerTest extends TestCase
{
    const EXAMPLE_URI = '/test';
    const EXAMPLE_TEXT = 'hello';

    /**
     * @var TextDocumentItem
     */
    private $document;

    /**
     * @var Position
     */
    private $position;

    /**
     * @var TextDocumentIdentifier
     */
    private $identifier;

    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var ObjectProphecy|ReferenceFinder
     */
    private $finder;

    /**
     * @var ObjectProphecy
     */
    private $locator;

    protected function setUp(): void
    {
        $this->finder = $this->prophesize(ReferenceFinder::class);
        $this->locator = $this->prophesize(DefinitionLocator::class);
        $this->workspace = new Workspace();

        $this->document = ProtocolFactory::textDocumentItem(__FILE__, self::EXAMPLE_TEXT);
        $this->workspace->open($this->document);
        $this->identifier = ProtocolFactory::textDocumentIdentifier(__FILE__);
        $this->identifier = new TextDocumentIdentifier(__FILE__);
        $this->position = new Position(0, 0);
    }

    public function testFindsReferences()
    {
        $document = TextDocumentBuilder::create(self::EXAMPLE_TEXT)
            ->language('php')
            ->uri(__FILE__)
            ->build()
        ;

        $this->finder->findReferences(
            $document,
            ByteOffset::fromInt(0)
        )->willYield([
            PotentialLocation::surely(new Location($document->uri(), ByteOffset::fromInt(2)))
        ])->shouldBeCalled();

        $tester = new HandlerTester($this->createReferencesHandler());

        $response = $tester->requestAndWait('textDocument/references', [
            'textDocument' => $this->identifier,
            'position' => $this->position,
            'context' => new ReferenceContext(false),
        ]);
        $locations = $response->result;
        $this->assertIsArray($locations);
        $this->assertCount(1, $locations);
        $lspLocation = reset($locations);
        $this->assertInstanceOf(LspLocation::class, $lspLocation);
    }

    public function testFindsReferencesIncludingDeclaration(): void
    {
        $document = TextDocumentBuilder::create(self::EXAMPLE_TEXT)
            ->language('php')
            ->uri(__FILE__)
            ->build()
        ;

        $this->finder->findReferences(
            $document,
            ByteOffset::fromInt(0)
        )->willYield([
            PotentialLocation::surely(new Location($document->uri(), ByteOffset::fromInt(2)))
        ])->shouldBeCalled();

        $this->locator->locateDefinition(
            $document,
            ByteOffset::fromInt(0)
        )->willReturn(new DefinitionLocation($document->uri(), ByteOffset::fromInt(2)))->shouldBeCalled();

        $tester = new HandlerTester($this->createReferencesHandler());

        $response = $tester->requestAndWait('textDocument/references', [
            'textDocument' => $this->identifier,
            'position' => $this->position,
            'context' => new ReferenceContext(true),
        ]);
        $locations = $response->result;
        $this->assertIsArray($locations);
        $this->assertCount(2, $locations);
        $lspLocation = reset($locations);
        $this->assertInstanceOf(LspLocation::class, $lspLocation);
    }

    public function testFindsReferencesIncludingDeclarationWhenDeclarationNotFound()
    {
        $document = TextDocumentBuilder::create(self::EXAMPLE_TEXT)
            ->language('php')
            ->uri(__FILE__)
            ->build()
        ;

        $this->finder->findReferences(
            $document,
            ByteOffset::fromInt(0)
        )->willYield([
            PotentialLocation::surely(new Location($document->uri(), ByteOffset::fromInt(2)))
        ])->shouldBeCalled();

        $this->locator->locateDefinition(
            $document,
            ByteOffset::fromInt(0)
        )->willReturn(new DefinitionLocation($document->uri(), ByteOffset::fromInt(2)))->willThrow(new CouldNotLocateDefinition('nope'));

        $tester = new HandlerTester($this->createReferencesHandler());

        $response = $tester->requestAndWait('textDocument/references', [
            'textDocument' => $this->identifier,
            'position' => $this->position,
            'context' => new ReferenceContext(true),
        ]);
        $locations = $response->result;
        $this->assertIsArray($locations);
        $this->assertCount(1, $locations);
        $lspLocation = reset($locations);
        $this->assertInstanceOf(LspLocation::class, $lspLocation);
    }

    private function createReferencesHandler(): ReferencesHandler
    {
        return new ReferencesHandler(
            $this->workspace,
            $this->finder->reveal(),
            $this->locator->reveal(),
            new LocationConverter($this->workspace),
            new ClientApi(TestRpcClient::create())
        );
    }
}
