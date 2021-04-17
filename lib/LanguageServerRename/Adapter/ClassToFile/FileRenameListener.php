<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\ClassToFile;

use Phpactor\Extension\LanguageServerRename\Model\FileRenamer;
use Phpactor\LanguageServerProtocol\MessageActionItem;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Event\TextDocumentClosed;
use Phpactor\LanguageServer\Event\TextDocumentOpened;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\EventDispatcher\ListenerProviderInterface;
use function Amp\asyncCall;
use function Amp\asyncCoroutine;
use function spl_object_hash;

final class FileRenameListener implements ListenerProviderInterface
{
    const RENAME_FRAME_MICROSECONDS = 100;

    /**
     * @var TextDocumentLocator
     */
    private $locator;

    /**
     * @var TextDocument
     */
    private $lastClosed;

    /**
     * @var string
     */
    private $lastClosedTime;

    /**
     * @var ClientApi
     */
    private $api;

    /**
     * @var FileRenamer
     */
    private $renamer;

    public function __construct(TextDocumentLocator $locator, ClientApi $api, FileRenamer $renamer)
    {
        $this->locator = $locator;
        $this->api = $api;
        $this->renamer = $renamer;
    }

    /**
     * {@inheritDoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof TextDocumentClosed) {
            return [[
                $this,
                'registerClose'
            ]];
        }

        if ($event instanceof TextDocumentOpened) {
            return [[
                $this,
                'registerOpen'
            ]];
        }

        return [];
    }

    public function registerClose(TextDocumentClosed $closed): void
    {
        $this->lastClosedTime = microtime(true);
        $this->lastClosed = $closed;
    }

    public function registerOpen(TextDocumentOpened $opened): void
    {
        if (!$this->lastClosedTime) {
            return;
        }

        if (microtime(true) - $this->lastClosedTime > self::RENAME_FRAME_MICROSECONDS) {
            $this->lastClosedTime = null;
            return;
        }

        asyncCall(function () use ($opened) {
            $item = yield $this->api->window()->showMessageRequest()->info(
                sprintf('Potential file move detected, move class contained in file?'),
                new MessageActionItem('Yes'),
                new MessageActionItem('No')
            );

            assert($item instanceof MessageActionItem);

            if ($item->title === 'No') {
                return;
            }

            yield $this->renamer->renameFile(
                $this->lastClosed->uri(),
                TextDocumentUri::fromString($opened->textDocument()->uriA)
            );
        });
    }
}
