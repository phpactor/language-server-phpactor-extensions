<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\LspCommand;

use LanguageServerProtocol\ApplyWorkspaceEditResponse;
use LanguageServerProtocol\TextDocumentItem;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\ClassAlreadyImportedException;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportClassCommand;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\LanguageServer\Workspace\CommandDispatcher;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class ImportClassCommandTest extends TestCase
{
    const EXAMPLE_CONTENT = 'hello this is some text';
    const EXAMPLE_PATH = '/foobar.php';


    /**
     * @var ImportClass
     */
    private $importClass;
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var TextEditConverter
     */
    private $converter;

    /**
     * @var TestRpcClient
     */
    private $rpcClient;

    /**
     * @var ImportClassCommand
     */
    private $command;

    protected function setUp(): void
    {
        $this->importClass = $this->prophesize(ImportClass::class);
        $this->workspace = new Workspace();
        $this->rpcClient = TestRpcClient::create();
        $this->converter = new TextEditConverter(new LocationConverter($this->workspace));
        $this->command = new ImportClassCommand($this->importClass->reveal(), $this->workspace, $this->converter, new ClientApi($this->rpcClient));
    }

    public function testImportClass(): void
    {
        $this->workspace->open(new TextDocumentItem('file:///foobar.php', 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            12,
            'Foobar'
        )->willReturn(TextEdits::one(
            TextEdit::create(12, 12, 'some replacement')
        ));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            'file:///foobar.php', 12, 'Foobar'
        ]);
        $expectedResponse = new ApplyWorkspaceEditResponse(true, null);
        $this->rpcClient->responseWatcher()->resolveLastResponse($expectedResponse);
        $result = \Amp\Promise\wait($promise);
        $this->assertEquals($expectedResponse, $result);
    }

    public function testNotifyOnError(): void
    {
        $this->workspace->open(new TextDocumentItem('file:///foobar.php', 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            12,
            'Foobar'
        )->willThrow(new TransformException('Sorry'));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            'file:///foobar.php', 12, 'Foobar'
        ]);

        self::assertNotNull($message = $this->rpcClient->transmitter()->shiftNotification());
        self::assertEquals('Sorry', $message->params['message']);
    }

    public function testIgnoreAlreadyImported(): void
    {
        $this->workspace->open(new TextDocumentItem('file:///foobar.php', 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            12,
            'Foobar'
        )->willThrow(new ClassAlreadyImportedException('Sorry', 'Goodbye'));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            'file:///foobar.php', 12, 'Foobar'
        ]);

        self::assertNull($this->rpcClient->transmitter()->shiftNotification());
    }
}
