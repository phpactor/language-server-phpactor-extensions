<?php

namespace Phpactor\Extension\LanguageServerRename\Listener;

use Amp\Promise;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\ActionDecider;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\RenamesResolver;
use Phpactor\Extension\LanguageServerRename\Model\Exception\CouldNotRename;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Extension\LanguageServerRename\Util\LocatedTextEditConverter;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\MessageActionItem;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\EventDispatcher\ListenerProviderInterface;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\delay;

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
     * @var RenamesResolver
     */
    private $renamesResolver;

    /**
     * @var TextDocumentLocator
     */
    private $locator;

    public function __construct(TextDocumentLocator $locator, ClientApi $api, FileRenamer $renamer, bool $interactive = true)
    {
        $this->api = $api;
        $this->renamer = $renamer;
        $this->decider = new ActionDecider();
        $this->renamesResolver = new RenamesResolver();
        $this->interactive = $interactive;
        $this->locator = $locator;
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
                yield $this->rename($changed);
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
    private function rename(FilesChanged $changed): Promise
    {
        return call(function () use ($changed) {
            $action = $this->decider->determineAction($changed);
            if ($action === ActionDecider::ACTION_NONE) {
                return;
            }

            if ($this->interactive) {
                $item = yield $this->api->window()->showMessageRequest()->info(
                    (function (string $action): string {
                        return sprintf(<<<'EOT'
Potential %s move detected. Rename any PSR compliant classes on disk?
Note this operation is destructive and changes may not sync back to the editor
EOT
                        , $action);
                    })($action),
                    new MessageActionItem(self::ANSWER_YES),
                    new MessageActionItem(self::ANSWER_NO)
                );

                if (!$item instanceof MessageActionItem) {
                    return;
                }

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
            }

            foreach ($map->toLocatedTextEdits() as $edit) {
                $success = @file_put_contents(
                    $edit->documentUri()->path(),
                    $edit->textEdits()->apply($this->locator->get($edit->documentUri())->__toString())
                );

                if (!$success) {
                    $this->api->window()->logMessage()->warning(sprintf(
                        'Could not save file "%s"',
                        $edit->documentUri()->__toString()
                    ));
                }
            }
        });
    }
}
