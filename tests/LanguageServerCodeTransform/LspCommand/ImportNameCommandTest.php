<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\LspCommand;

use Amp\Promise;
use Phpactor\LanguageServerProtocol\ApplyWorkspaceEditResponse;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\LanguageServer\Workspace\CommandDispatcher;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use Prophecy\Prophecy\ObjectProphecy;

class ImportNameCommandTest extends TestCase
{
    const EXAMPLE_CONTENT = 'hello this is some text';
    const EXAMPLE_PATH = '/foobar.php';
    const EXAMPLE_OFFSET = 12;
    const EXAMPLE_PATH_URI = 'file:///foobar.php';

    /**
     * @var ObjectProphecy<ImportName>
     */
    private $importName;

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
        $this->importName = $this->prophesize(ImportName::class);
        $this->workspace = new Workspace();
        $this->rpcClient = TestRpcClient::create();
        $this->converter = new TextEditConverter(new LocationConverter($this->workspace));
        $this->command = new ImportNameCommand(
            $this->importName->reveal(),
            $this->workspace,
            $this->converter,
            new ClientApi($this->rpcClient)
        );
    }

    public function testImportClass(): void
    {
        $this->workspace->open(
            new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT)
        );

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Foobar')
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
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

    public function testImportFunction(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forFunction('Foobar')
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
        ));

        $promise = (new CommandDispatcher([
            ImportNameCommand::NAME => $this->command
        ]))->dispatch(ImportNameCommand::NAME, [
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'function',
            'Foobar'
        ]);

        $this->assertWorkspaceResponse($promise);
    }

    public function testNotifyOnError(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Foobar')
        )->willThrow(new TransformException('Sorry'));

        $promise = (new CommandDispatcher([
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

    public function testIgnoreSameAlreadyImported(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Acme\Foobar')
        )->willThrow(new NameAlreadyImportedException(NameImport::forClass('Foobar'), 'Acme\Foobar'));

        $promise = (new CommandDispatcher([
            ImportNameCommand::NAME => $this->command
        ]))->dispatch(ImportNameCommand::NAME, [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'class', 'Acme\Foobar'
        ]);

        self::assertNull($this->rpcClient->transmitter()->shiftNotification());
    }

    public function testAutomaticallyAddAlias(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Acme\Foobar')
        )->willThrow(new NameAlreadyImportedException(NameImport::forClass('Acme\Foobar'), 'NotMyClass\Foobar'));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Acme\Foobar', 'AcmeFoobar'),
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
        ))->shouldBeCalled();

        $promise = (new CommandDispatcher([
            ImportNameCommand::NAME => $this->command
        ]))->dispatch(ImportNameCommand::NAME, [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'class', 'Acme\Foobar'
        ]);

        $this->assertWorkspaceResponse($promise);
    }

    public function testHandleAlreadyExistingAlias(): void
    {
        $this->workspace->open(new TextDocumentItem(self::EXAMPLE_PATH_URI, 'php', 1, self::EXAMPLE_CONTENT));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Acme\Foobar', 'AcmeFoobar'),
        )->willThrow(new AliasAlreadyUsedException(NameImport::forClass('Acme\Foobar', 'AcmeFoobar')));

        $this->importName->importName(
            SourceCode::fromStringAndPath(self::EXAMPLE_CONTENT, self::EXAMPLE_PATH),
            ByteOffset::fromInt(self::EXAMPLE_OFFSET),
            NameImport::forClass('Acme\Foobar', 'AliasedFoobar'),
        )->willReturn(TextEdits::one(
            TextEdit::create(self::EXAMPLE_OFFSET, self::EXAMPLE_OFFSET, 'some replacement')
        ));

        $promise = (new CommandDispatcher([
            ImportNameCommand::NAME => $this->command
        ]))->dispatch(ImportNameCommand::NAME, [
            self::EXAMPLE_PATH_URI, self::EXAMPLE_OFFSET, 'class', 'Acme\Foobar', 'AcmeFoobar'
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
