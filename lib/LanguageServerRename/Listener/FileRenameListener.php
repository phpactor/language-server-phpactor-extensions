<?php

namespace Phpactor\Extension\LanguageServerRename\Listener;

use Amp\Promise;
use Error;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\ActionDecider;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer;
use Phpactor\Extension\LanguageServerRename\Util\LocatedTextEditConverter;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\MessageActionItem;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\LanguageServer\Event\TextDocumentClosed;
use Phpactor\LanguageServer\Event\TextDocumentOpened;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\EventDispatcher\ListenerProviderInterface;
use function Amp\asyncCall;
use function Amp\asyncCoroutine;
use function Amp\call;
use function spl_object_hash;

final class FileRenameListener implements ListenerProviderInterface
{
    public const ACTION_FILE = 'file';
    public const ACTION_FOLDER = 'folder';
    public const ACTION_NONE = 'none';


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

    /**
     * @var ActionDecider
     */
    private $decider;

    /**
     * @var bool
     */
    private $interactive;

    public function __construct(TextDocumentLocator $locator, ClientApi $api, FileRenamer $renamer, bool $interactive = true)
    {
        $this->locator = $locator;
        $this->api = $api;
        $this->renamer = $renamer;
        $this->decider = new ActionDecider();
        $this->interactive = $interactive;
    }

    /**
     * {@inheritDoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof FilesChanged) {
            return [[
                $this,
                'handleEvent'
            ]];
        }

        return [];
    }

    public function handleEvent(FilesChanged $changed): void
    {
        $action = $this->decider->determineAction($changed);

        if ($action === self::ACTION_NONE) {
            return;
        }

        asyncCall(function () use ($changed, $action) {
            if ($action === self::ACTION_FILE) {
                yield $this->moveFile($changed);
                return;
            }

            if ($action === self::ACTION_FOLDER) {
                $this->moveFolder($changed);
                return;
            }
        });
    }

    private function moveFile(FilesChanged $changed): Promise
    {
        return call(function () use ($changed) {
            if ($this->interactive) {
                $item = yield $this->api->window()->showMessageRequest()->info(
                    sprintf('Potential file move detected, move class contained in file?'),
                    new MessageActionItem('Yes'),
                    new MessageActionItem('No')
                );

                assert($item instanceof MessageActionItem);

                if ($item->title === 'No') {
                    return;
                }
            }

            $closed = $changed->byType(FileChangeType::DELETED)->first();
            $opened = $changed->byType(FileChangeType::CREATED)->first();

            $map = yield $this->renamer->renameFile(
                TextDocumentUri::fromString($closed->uri),
                TextDocumentUri::fromString($opened->uri)
            );
            $this->api->workspace()->applyEdit(LocatedTextEditConverter::toWorkspaceEdit($map));
        });
    }

    private function moveFolder(FilesChanged $changed): void
    {
        $this->api->window()->showMessage()->info(
            sprintf('Potential folder move detected, no action available though'),
        );
    }
}
