<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Tests\Unit\Handler;

use Phpactor\Extension\LanguageServerBridge\TextDocument\WorkspaceTextDocumentLocator;
use Phpactor\LanguageServerProtocol\DefinitionRequest;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerReferenceFinder\Handler\GotoDefinitionHandler;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;
use Prophecy\Prophecy\ObjectProphecy;

class GotoDefinitionHandlerTest extends TestCase
{
    const EXAMPLE_URI = 'file:///test';
    const EXAMPLE_TEXT = 'hello';

    /**
     * @var ObjectProphecy|DefinitionLocator
     */
    private $locator;

    protected function setUp(): void
    {
        $this->locator = $this->prophesize(DefinitionLocator::class);
    }

    public function testGoesToDefinition(): void
    {
        $document = TextDocumentBuilder::create(
            self::EXAMPLE_TEXT
        )->uri(self::EXAMPLE_URI)->language('php')->build();

        $this->locator->locateDefinition(
            $document,
            ByteOffset::fromInt(0)
        )->willReturn(
            new DefinitionLocation($document->uri(), ByteOffset::fromInt(2))
        )->shouldBeCalled();

        $builder = LanguageServerTesterBuilder::create();
        $tester = $builder->addHandler(new GotoDefinitionHandler(
            $builder->workspace(),
            $this->locator->reveal(),
            new LocationConverter(new WorkspaceTextDocumentLocator($builder->workspace()))
        ))->build();
        $tester->textDocument()->open(self::EXAMPLE_URI, self::EXAMPLE_TEXT);

        $response = $tester->requestAndWait(DefinitionRequest::METHOD, [
            'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
            'position' => ProtocolFactory::position(0, 0),
        ]);

        $location = $response->result;
        $this->assertInstanceOf(Location::class, $location);
        $this->assertEquals('file:///test', $location->uri);
        $this->assertEquals(2, $location->range->start->character);
    }
}
