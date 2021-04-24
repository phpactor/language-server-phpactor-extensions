<?php

namespace Phpactor\Extension\LanguageServerRename\Listener;

use Amp\Promise;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\ActionDecider;
use Phpactor\Extension\LanguageServerRename\Model\Exception\CouldNotRename;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Extension\LanguageServerRename\Util\LocatedTextEditConverter;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\MessageActionItem;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\EventDispatcher\ListenerProviderInterface;
use function Amp\asyncCall;
use function Amp\call;

final class FileRenameListener implements ListenerProviderInterface
{
    public const ACTION_FILE = 'file';
    public const ACTION_FOLDER = 'folder';
    public const ACTION_NONE = 'none';
    const ANSWER_YES = 'Yes';
    const ANSWER_NO = 'No';

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

    /**
     * @var LocatedTextEditConverter
     */
    private $converter;

    public function __construct(LocatedTextEditConverter $converter, ClientApi $api, FileRenamer $renamer, bool $interactive = true)
    {
        $this->api = $api;
        $this->renamer = $renamer;
        $this->decider = new ActionDecider();
        $this->interactive = $interactive;
        $this->converter = $converter;
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
            try {
                if ($action === self::ACTION_FILE) {
                    yield $this->moveFile($changed);
                    return;
                }

                if ($action === self::ACTION_FOLDER) {
                    $this->moveFolder($changed);
                    return;
                }
            } catch (CouldNotRename $couldNotRename) {
                $this->api->window()->showMessage()->error(sprintf(
                    'Could not rename: %s',
                    $couldNotRename->getMessage()
                ));
            }
        });
    }

    /**
     * @return Promise<void>
     */
    private function moveFile(FilesChanged $changed): Promise
    {
        return call(function () use ($changed) {
            if ($this->interactive) {
                $item = yield $this->api->window()->showMessageRequest()->info(
                    sprintf('Potential file move detected, move class contained in file?'),
                    new MessageActionItem(self::ANSWER_YES),
                    new MessageActionItem(self::ANSWER_NO)
                );

                assert($item instanceof MessageActionItem);

                if ($item->title === self::ANSWER_NO) {
                    return;
                }
            }

            $closed = $changed->byType(FileChangeType::DELETED)->first();
            $opened = $changed->byType(FileChangeType::CREATED)->first();

            $map = yield $this->renamer->renameFile(
                TextDocumentUri::fromString($closed->uri),
                TextDocumentUri::fromString($opened->uri)
            );
            $this->api->workspace()->applyEdit($this->converter->toWorkspaceEdit($map));
        });
    }

    /**
     * @return Promise<void>
     */
    private function moveFolder(FilesChanged $changed): Promise
    {
        return call(function () use ($changed) {
            if ($this->interactive) {
                $item = yield $this->api->window()->showMessageRequest()->info(
                    sprintf('Potential folder move detected, move all classes contained in folder?'),
                    new MessageActionItem(self::ANSWER_YES),
                    new MessageActionItem(self::ANSWER_NO)
                );

                assert($item instanceof MessageActionItem);

                if ($item->title === self::ANSWER_NO) {
                    return;
                }
            }


            $map = LocatedTextEditsMap::create();

            foreach ($this->renamesResolver->resolve($changed) as $rename) {
                $map = $map->merge(yield $this->renamer->renameFile(
                    TextDocumentUri::fromString($rename->from),
                    TextDocumentUri::fromString($rename->to)
                ));
                assert($map instanceof LocatedTextEditsMap);
            }

            $this->api->workspace()->applyEdit($this->converter->toWorkspaceEdit($map));
        });
    }
}
