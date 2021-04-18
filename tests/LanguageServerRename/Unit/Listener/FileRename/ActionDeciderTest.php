<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Listener\FileRename;

use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\ActionDecider;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\Event\FilesChanged;

class ActionDeciderTest extends TestCase
{
    const EXAMPLE_FILE_1 = 'file:///example1';
    const EXAMPLE_FILE_2 = 'file:///example2';

    /**
     * @dataProvider provideDecideFile
     * @dataProvider provideDecideFolder
     */
    public function testDecide(FilesChanged $changed, string $expected)
    {
        self::assertEquals($expected, (new ActionDecider())->determineAction($changed));
    }

    /**
     * @return Generator<mixed>
     */
    public function provideDecideFile(): Generator
    {
        yield [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED)
            ),
            ActionDecider::ACTION_NONE
        ];

        yield [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED),
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED),
            ),
            ActionDecider::ACTION_NONE
        ];

        yield [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::DELETED),
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::DELETED),
            ),
            ActionDecider::ACTION_NONE
        ];

        yield 'create and delete = move' => [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED),
                new FileEvent(self::EXAMPLE_FILE_2, FileChangeType::DELETED),
            ),
            ActionDecider::ACTION_FILE
        ];
    }

    /**
     * @return Generator<mixed>
     */
    public function provideDecideFolder(): Generator
    {
        yield [
            new FilesChanged(
                new FileEvent('file:///one/two/file1', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file2', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file1', FileChangeType::CREATED),
            ),
            ActionDecider::ACTION_NONE
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///one/two/file1', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file2', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file1', FileChangeType::CREATED),
                new FileEvent('file:///one/two/file2', FileChangeType::CREATED),
            ),
            ActionDecider::ACTION_FOLDER
        ];
    }
}
