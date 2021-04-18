<?php

namespace Phpactor\Extension\LanguageServerIndexer\Watcher;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Phpactor\LanguageServer\Event\FilesChanged;
use Psr\EventDispatcher\ListenerProviderInterface;

class LanguageServerWatcher implements Watcher, WatcherProcess, ListenerProviderInterface
{
    /**
     * @var Deferred
     */
    private $deferred;

    public function __consturct()
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
        $this->deferred->resolve($filesChanged);
    }

    public function stop(): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function wait(): Promise
    {
        return $this->deferred->promise();
    }
}
