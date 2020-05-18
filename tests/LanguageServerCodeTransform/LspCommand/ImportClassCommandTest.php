<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\LspCommand;

use Amp\Promise;
use LanguageServerProtocol\ApplyWorkspaceEditResponse;
use LanguageServerProtocol\TextDocumentItem;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
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
use Prophecy\Prophecy\ObjectProphecy;

class ImportClassCommandTest extends TestCase
{
    const EXAMPLE_CONTENT = 'hello this is some text';
    const EXAMPLE_PATH = '/foobar.php';
    const EXAMPLE_OFFSET = 12;
    const EXAMPLE_PATH_URI = 'file:///foobar.php';


    /**
     * @var ObjectProphecy<ImportClass>
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
        $this->command = new ImportClassCommand(
            $this->importClass->reveal(),
            $this->workspace,
            $this->converter,
            new ClientApi($this->rpcClient)
        );
    }

    public function testImportClass(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Foobar'
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
        ));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'Foobar'
        ]);

        $this->assertWorkspaceResponse($promise);
    }

    public function testNotifyOnError(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Foobar'
        )->willThrow(new TransformException('Sorry'));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'Foobar'
        ]);

        self::assertNotNull($message = $this->rpcClient->transmitter()->shiftNotification());
        self::assertEquals('Sorry', $message->params['message']);
    }

    public function testIgnoreSameAlreadyImported(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Acme\Foobar'
        )->willThrow(new ClassAlreadyImportedException('Foobar', 'Acme\Foobar'));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'Acme\Foobar'
        ]);

        self::assertNull($this->rpcClient->transmitter()->shiftNotification());
    }

    public function testAutomaticallyAddAlias(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Acme\Foobar'
        )->willThrow(new ClassAlreadyImportedException('Foobar', 'NotMyClass\Foobar'));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Acme\Foobar',
            'AcmeFoobar',
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
        ))->shouldBeCalled();

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'Acme\Foobar'
        ]);

        $this->assertWorkspaceResponse($promise);
    }

    public function testHandleAlreadyExistingAlias(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Acme\Foobar',
            'AcmeFoobar',
        )->willThrow(new AliasAlreadyUsedException('AcmeFoobar'));

        $this->importClass->importClass(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            self::EXAMPLE_OFFSET,
            'Acme\Foobar',
            'AliasedAcmeFoobar',
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
        ));

        $promise = (new CommandDispatcher([
            'import_class' => $this->command
        ]))->dispatch('import_class', [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'Acme\Foobar', 'AcmeFoobar'
        ]);

        $this->assertWorkspaceResponse($promise);
    }

    private function assertWorkspaceResponse(Promise $promise): void
    {
        $expectedResponse = new ApplyWorkspaceEditResponse(true, null);
        $this->rpcClient->responseWatcher()->resolveLastResponse($expectedResponse);
        $result = \Amp\Promise\wait($promise);
        $this->assertEquals($expectedResponse, $result);
    }
}
