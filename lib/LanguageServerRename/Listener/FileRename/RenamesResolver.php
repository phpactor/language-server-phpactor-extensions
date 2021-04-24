<?php

namespace Phpactor\Extension\LanguageServerRename\Listener\FileRename;

use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\TextDocument\TextDocumentUri;
use Webmozart\PathUtil\Path;
use function array_sum;

class RenamesResolver
{
    /**
     * @return Rename[]
     */
    public function resolve(FilesChanged $changed): array
    {
        $eventCount = count($changed->events());
        $sum = array_sum(array_map(function (FileEvent $event) {
            return $event->type;
        }, $changed->events()));

        if ($sum !== ($eventCount / 2) + ($eventCount / 2) * 3) {
            return [];
        }

        if ($eventCount === 2 && $sum === 4) {
            return [
                new Rename(
                    TextDocumentUri::fromString($changed->byType(FileChangeType::DELETED)->first()->uri),
                    TextDocumentUri::fromString($changed->byType(FileChangeType::CREATED)->first()->uri),
                )
            ];
        }

        $dirCreated = $this->commonPrefix($changed->byType(FileChangeType::CREATED));
        $dirDeleted = $this->commonPrefix($changed->byType(FileChangeType::DELETED));

        $renames = [];
        foreach ($changed->byType(FileChangeType::CREATED)->events() as $event) {
            assert($event instanceof FileEvent);
            $suffix = substr($event->uri, strlen($dirCreated));
            if (empty($suffix)) {
                continue;
            }
            $renames[] = new Rename(
                TextDocumentUri::fromString(Path::join([$dirDeleted, $suffix])),
                TextDocumentUri::fromString(Path::join([$dirCreated, $suffix])),
            );
        }

        return $renames;
    }

    private function commonPrefix(FilesChanged $filesChanged): string
    {
        return Path::getLongestCommonBasePath(array_map(function (FileEvent $event) {
            return Path::getDirectory($event->uri);
        }, $filesChanged->events()));
    }
}

