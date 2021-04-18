<?php

namespace Phpactor\Extension\LanguageServerIndexer\Watcher;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\EventDispatcher\ListenerProviderInterface;
use function Amp\call;

class LanguageServerWatcher implements Watcher, WatcherProcess, ListenerProviderInterface
{
    /**
     * @var Deferred<FilesChanged>
     */
    private $deferred;

    public function __construct()
    {
        $this->deferred = new Deferred();
    }

    /**
     * {@inheritDoc}
     */
    public function watch(): Promise
    {
        return new Success($this);
    }

    /**
     * {@inheritDoc}
     */
    public function isSupported(): Promise
    {
        return new Success(true);
    }

    /**
     * {@inheritDoc}
     */
    public function describe(): string
    {
        return 'LSP file events';
    }

    /**
     * {@inheritDoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof FilesChanged) {
            return  [[$this, 'enqueue']];
        }

        return [];
    }

    public function enqueue(FilesChanged $filesChanged): void
    {
        foreach ($filesChanged->events() as $changedFile) {
            $this->deferred->resolve($changedFile);
        }
    }

    public function stop(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function wait(): Promise
    {
        return call(function () {
            $event = yield $this->deferred->promise();
            assert($event instanceof FileEvent);

            $this->deferred = new Deferred();

            return ModifiedFileBuilder::fromPath(
                TextDocumentUri::fromString($event->uri)->path(),
            )->asFile()->build();
        });
    }
}
