<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Listener;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerBridge\TextDocument\FilesystemWorkspaceLocator;
use Phpactor\Extension\LanguageServerRename\Listener\FileRenameListener;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer\TestFileRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Extension\LanguageServerRename\Tests\IntegrationTestCase;
use Phpactor\Extension\LanguageServerRename\Util\LocatedTextEditConverter;
use Phpactor\LanguageServerProtocol\DidChangeWatchedFilesParams;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class FileRenameListenerTest extends IntegrationTestCase
{
    public function testMoveFileInteractive(): void
    {
        $server = $this->createServerWithListener();
        $server->notify('workspace/didChangeWatchedFiles', new DidChangeWatchedFilesParams([
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
        ]));

        $server->transmitter()->shift();
        $dialog = $server->transmitter()->shiftRequest();
        self::assertNotNull($dialog);
        self::assertEquals('window/showMessageRequest', $dialog->method);
        self::assertStringContainsString('file move', $dialog->params['message']);
    }

    public function testMoveFolderInteractive(): void
    {
        $server = $this->createServerWithListener();

        $server->notify('workspace/didChangeWatchedFiles', new DidChangeWatchedFilesParams([
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
        ]));

        $server->transmitter()->shift();
        $dialog = $server->transmitter()->shiftRequest();
        self::assertNotNull($dialog);
        self::assertEquals('window/showMessageRequest', $dialog->method);
        self::assertStringContainsString('folder move', $dialog->params['message']);
    }

    public function testMoveFile(): void
    {
        $this->workspace()->put('file1', '');
        $this->workspace()->put('file2', '');
        $server = $this->createServerWithListener(false, false, [
            $this->workspace()->path('file2') => TextEdits::one(TextEdit::create(0, 0, 'Hello')),
        ]);

        $server->notify('workspace/didChangeWatchedFiles', new DidChangeWatchedFilesParams([
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
        ]));

        self::assertEquals('Hello', $this->workspace()->getContents('file2'));
    }

    public function testShowsErrorToClientWhenCannotRename(): void
    {
        $server = $this->createServerWithListener(false, true);

        $server->notify('workspace/didChangeWatchedFiles', new DidChangeWatchedFilesParams([
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
        ]));

        $server->transmitter()->shift();

        $apply = $server->transmitter()->shift();

        self::assertNotNull($apply);
        self::assertEquals('window/showMessage', $apply->method);
        self::assertEquals('Could not rename: There was a problem', $apply->params['message']);
    }

    public function testMoveFolder(): void
    {
        $server = $this->createServerWithListener(false);

        $server->notify('workspace/didChangeWatchedFiles', new DidChangeWatchedFilesParams([
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
        ]));

        $server->transmitter()->shift();
        $this->addToAssertionCount(1);
    }

    private function createServerWithListener(bool $interactive = true, bool $willFail = false, array $workspaceEdits = []): LanguageServerTester
    {
        $builder = LanguageServerTesterBuilder::createBare()
            ->enableFileEvents();
        $builder->addListenerProvider($this->createListener($builder, $interactive, $willFail, $workspaceEdits));
        $server = $builder->build();
        $server->initialize();
        return $server;
    }

    private function createListener(LanguageServerTesterBuilder $builder, bool $interactive = true, bool $willError = false, array $workspaceEdits = []): FileRenameListener
    {
        return new FileRenameListener(
            new FilesystemWorkspaceLocator(),
            $builder->clientApi(),
            new TestFileRenamer($willError, new LocatedTextEditsMap($workspaceEdits)),
            $interactive
        );
    }
}
