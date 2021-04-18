<?php

namespace Phpactor\Extension\LanguageServerRename\Listener\FileRename;

use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\Event\FilesChanged;
use function array_sum;

class ActionDecider
{
    const ACTION_FILE = 'file';
    const ACTION_FOLDER = 'folder';
    const ACTION_NONE = 'none';

    /**
     * @return self::ACTION_*
     */
    public function determineAction(FilesChanged $changed): string
    {
        $eventCount = count($changed->events());
        $sum = array_sum(array_map(function (FileEvent $event) {
            return $event->type;
        }, $changed->events()));

        if ($eventCount === 2 && $sum === 4) {
            return self::ACTION_FILE;
        }

        if ($sum === ($eventCount / 2) + ($eventCount / 2) * 3) {
            return self::ACTION_FOLDER;
        }

        return self::ACTION_NONE;
    }
}
