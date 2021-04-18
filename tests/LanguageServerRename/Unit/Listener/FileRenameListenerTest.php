<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Listener;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Listener\FileRenameListener;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer\NullFileRenamer;
use Phpactor\LanguageServerProtocol\DidChangeWatchedFilesParams;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use function Amp\Promise\wait;
use function Amp\delay;

class FileRenameListenerTest extends TestCase
{
    public function testMoveFile(): void
    {
        $builder = LanguageServerTesterBuilder::createBare()
            ->enableFileEvents();
        $builder->addListenerProvider(new FileRenameListener(
            InMemoryDocumentLocator::new(),
            $builder->clientApi(),
            new NullFileRenamer()
        ));
        $server = $builder->build();
        $server->initialize();
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

    public function testMoveFolder(): void
    {
        $builder = LanguageServerTesterBuilder::createBare()
            ->enableFileEvents();
        $builder->addListenerProvider(new FileRenameListener(
            InMemoryDocumentLocator::new(),
            $builder->clientApi(),
            new NullFileRenamer()
        ));
        $server = $builder->build();
        $server->initialize();
        $server->notify('workspace/didChangeWatchedFiles', new DidChangeWatchedFilesParams([
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
            new FileEvent('file:///file1', FileChangeType::DELETED),
            new FileEvent('file:///file2', FileChangeType::CREATED),
        ]));

        $server->transmitter()->shift();
        $dialog = $server->transmitter()->shiftNotification();
        self::assertNotNull($dialog);
        self::assertEquals('window/showMessage', $dialog->method);
        self::assertStringContainsString('folder move', $dialog->params['message']);
    }
}
