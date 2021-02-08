<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Integration\Handler;

use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\InMemoryRenamer;
use Phpactor\Extension\LanguageServerRename\Tests\IntegrationTestCase;
use Phpactor\LanguageServerProtocol\PrepareRenameParams;
use Phpactor\LanguageServerProtocol\PrepareRenameRequest;
use Phpactor\LanguageServerProtocol\RenameParams;
use Phpactor\LanguageServerProtocol\RenameRequest;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\TextEdit as PhpactorTextEdit;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class RenameHandlerTest extends IntegrationTestCase
{
    const EXAMPLE_FILE = 'file:///Foobar.php';

    /**
     * @var LanguageServerTester
     */
    private $tester;

    /**
     * @var InMemoryRenamer
     */
    private $renamer;

    public function testRegistersCapabilities(): void
    {
        $this->initialize(null, []);
        $result = $this->tester->initialize();
        self::assertTrue($result->capabilities->renameProvider->prepareProvider);
    }

    public function testPrepareRenameReturnsNullIfPrepareAnything(): void
    {
        $this->initialize(null, []);
        $this->tester->textDocument()->open(self::EXAMPLE_FILE, '<?php');

        $response = $this->tester->requestAndWait(
            PrepareRenameRequest::METHOD,
            new PrepareRenameParams(
                ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_FILE),
                ProtocolFactory::position(0, 0),
            )
        );

        $this->tester->assertSuccess($response);
        self::assertNull($response->result);
    }

    public function testPrepareRename(): void
    {
        $expectedCharOffset = 3;

        $this->initialize(ByteOffsetRange::fromInts(0, $expectedCharOffset), [
            new RenameResult(TextEdits::none(), TextDocumentUri::fromString('file:///foobar.php'))
        ]);
        $this->tester->textDocument()->open(self::EXAMPLE_FILE, '<?php');

        $response = $this->tester->requestAndWait(
            PrepareRenameRequest::METHOD,
            new PrepareRenameParams(
                ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_FILE),
                ProtocolFactory::position(0, 0),
            )
        );

        $this->tester->assertSuccess($response);
        self::assertEquals(ProtocolFactory::range(0, 0, 0, $expectedCharOffset), $response->result);
    }

    public function testRename(): void
    {
        $expectedUri = TextDocumentUri::fromString(self::EXAMPLE_FILE);
        $this->initialize(ByteOffsetRange::fromInts(0, 0), [
            new RenameResult(
                TextEdits::one(
                    TextEdit::create(ByteOffset::fromInt(1), 0, 'foobar')
                ),
                $expectedUri,
            )
        ]);
        $this->tester->textDocument()->open(self::EXAMPLE_FILE, '<?php');

        $response = $this->tester->requestAndWait(
            RenameRequest::METHOD,
            new RenameParams(
                ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_FILE),
                ProtocolFactory::position(0, 0),
                'foobar'
            )
        );

        $this->tester->assertSuccess($response);
        assert($response->result instanceof WorkspaceEdit);
        $edit = $response->result->changes[0];
        assert($edit instanceof TextDocumentEdit);
        self::assertEquals(self::EXAMPLE_FILE, $edit->textDocument->uri);
        $edit = reset($edit->edits);
        assert($edit instanceof PhpactorTextEdit);
        self::assertEquals('foobar', $edit->newText);
    }

    protected function initialize(?ByteOffsetRange $range, array $results): void
    {
        $container = $this->container([
            'range' => $range,
            'results' => $results,
        ]);
        $this->tester = $container->get(LanguageServerBuilder::class)->tester(
            ProtocolFactory::initializeParams($this->workspace()->path())
        );
        $this->renamer = $container->get(InMemoryRenamer::class);
    }
}
