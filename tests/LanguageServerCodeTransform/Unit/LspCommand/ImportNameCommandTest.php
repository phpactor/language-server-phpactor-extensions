<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\LspCommand;

use Amp\Promise;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServerNameImport\Model\NameImportResult;
use Phpactor\Extension\LanguageServerNameImport\Service\NameImport;
use Phpactor\LanguageServer\Core\Command\CommandDispatcher;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\ApplyWorkspaceEditResponse;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Prophecy\Prophecy\ObjectProphecy;

class ImportNameCommandTest extends TestCase
{
    const EXAMPLE_CONTENT = 'hello this is some text';
    const EXAMPLE_PATH = '/foobar.php';
    const EXAMPLE_OFFSET = 12;
    const EXAMPLE_PATH_URI = 'file:///foobar.php';

    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var TestRpcClient
     */
    private $rpcClient;

    /**
     * @var ImportNameCommand
     */
    private $command;

    /**
     * @var ObjectProphecy<TextEdit>
     */
    private $textEditProphecy;

    /**
     * @var ObjectProphecy<NameImport>
     */
    private $nameImportProphecy;

    protected function setUp(): void
    {
        $this->textEditProphecy = $this->prophesize(TextEdit::class);
        $this->nameImportProphecy = $this->prophesize(NameImport::class);
        $this->workspace = new Workspace();
        $this->rpcClient = TestRpcClient::create();
        $this->command = new ImportNameCommand(
            $this->nameImportProphecy->reveal(),
            new ClientApi($this->rpcClient)
        );
    }

    public function testImportClass(): void
    {
        $this->workspace->open(
            new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT)
        );

        $this->nameImportProphecy->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            'Foobar',
            null
        )->willReturn(NameImportResult::createResult(
            [$this->textEditProphecy->reveal()],
            \Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport::forClass('Foobar')
        ));

        $promise = (new CommandDispatcher([
            ImportNameCommand::NAME => $this->command
        ]))->dispatch(ImportNameCommand::NAME, [
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            'Foobar'
        ]);

        $this->assertWorkspaceResponse($promise);
    }

    public function testNotifyOnError(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->nameImportProphecy->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            'Foobar',
            null
        )->willReturn(NameImportResult::createErrorResult(new TransformException('Sorry')));

        (new CommandDispatcher([
            ImportNameCommand::NAME => $this->command
        ]))->dispatch(ImportNameCommand::NAME, [
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            'Foobar'
        ]);

        self::assertNotNull($message = $this->rpcClient->transmitter()->shiftNotification());
        self::assertEquals('Sorry', $message->params['message']);
    }

    private function assertWorkspaceResponse(Promise $promise): void
    {
        $expectedResponse = new ApplyWorkspaceEditResponse(true, null);
        $this->rpcClient->responseWatcher()->resolveLastResponse($expectedResponse);
        $result = \Amp\Promise\wait($promise);
        $this->assertEquals($expectedResponse, $result);
    }
}
