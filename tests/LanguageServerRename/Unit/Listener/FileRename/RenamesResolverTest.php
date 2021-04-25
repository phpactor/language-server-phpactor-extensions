<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Listener\FileRename;

use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\Rename;
use Phpactor\Extension\LanguageServerRename\Listener\FileRename\RenamesResolver;
use Phpactor\LanguageServerProtocol\FileChangeType;
use Phpactor\LanguageServerProtocol\FileEvent;
use Phpactor\LanguageServer\Event\FilesChanged;
use Phpactor\TextDocument\TextDocumentUri;

class RenamesResolverTest extends TestCase
{
    const EXAMPLE_FILE_1 = 'file:///example1';
    const EXAMPLE_FILE_2 = 'file:///example2';

    /**
     * @dataProvider provideDecideFile
     * @dataProvider provideDecideFolder
     */
    public function testDecide(FilesChanged $changed, array $expected): void
    {
        self::assertEquals($expected, (new RenamesResolver())->resolve($changed));
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
            []
        ];

        yield [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED),
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED),
            ),
            [
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::DELETED),
                new FileEvent(self::EXAMPLE_FILE_2, FileChangeType::CREATED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString(self::EXAMPLE_FILE_1),
                    TextDocumentUri::fromString(self::EXAMPLE_FILE_2)
                )
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::DELETED),
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::DELETED),
            ),
            []
        ];

        yield 'create and delete = move' => [
            new FilesChanged(
                new FileEvent(self::EXAMPLE_FILE_1, FileChangeType::CREATED),
                new FileEvent(self::EXAMPLE_FILE_2, FileChangeType::DELETED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString(self::EXAMPLE_FILE_2),
                    TextDocumentUri::fromString(self::EXAMPLE_FILE_1),
                )
            ]
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
            [
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///one/two/file1', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file2', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file1', FileChangeType::CREATED),
                new FileEvent('file:///one/two/file2', FileChangeType::CREATED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString('/one/two/file1'),
                    TextDocumentUri::fromString('/one/two/file1'),
                ),
                new Rename(
                    TextDocumentUri::fromString('/one/two/file2'),
                    TextDocumentUri::fromString('/one/two/file2'),
                ),
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///one/two/file1', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file2', FileChangeType::DELETED),
                new FileEvent('file:///one/four/file1', FileChangeType::CREATED),
                new FileEvent('file:///one/four/file2', FileChangeType::CREATED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString('/one/two/file1'),
                    TextDocumentUri::fromString('/one/four/file1'),
                ),
                new Rename(
                    TextDocumentUri::fromString('/one/two/file2'),
                    TextDocumentUri::fromString('/one/four/file2'),
                ),
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///one/two/file1', FileChangeType::DELETED),
                new FileEvent('file:///one/two/file2', FileChangeType::DELETED),
                new FileEvent('file:///one/two/three/four/file1', FileChangeType::CREATED),
                new FileEvent('file:///one/two/three/four/file2', FileChangeType::CREATED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString('/one/two/file1'),
                    TextDocumentUri::fromString('/one/two/three/four/file1'),
                ),
                new Rename(
                    TextDocumentUri::fromString('/one/two/file2'),
                    TextDocumentUri::fromString('/one/two/three/four/file2'),
                ),
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///one/two/file1', FileChangeType::CREATED),
                new FileEvent('file:///one/two/file2', FileChangeType::CREATED),
                new FileEvent('file:///one/two/three/four/file1', FileChangeType::DELETED),
                new FileEvent('file:///one/two/three/four/file2', FileChangeType::DELETED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString('/one/two/three/four/file1'),
                    TextDocumentUri::fromString('/one/two/file1'),
                ),
                new Rename(
                    TextDocumentUri::fromString('/one/two/three/four/file2'),
                    TextDocumentUri::fromString('/one/two/file2'),
                ),
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///file1', FileChangeType::DELETED),
                new FileEvent('file:///file2', FileChangeType::DELETED),
                new FileEvent('file:///two/file1', FileChangeType::CREATED),
                new FileEvent('file:///two/file2', FileChangeType::CREATED),
            ),
            [
                new Rename(
                    TextDocumentUri::fromString('/file1'),
                    TextDocumentUri::fromString('/two/file1'),
                ),
                new Rename(
                    TextDocumentUri::fromString('/file2'),
                    TextDocumentUri::fromString('/two/file2'),
                ),
            ]
        ];

        yield [
            new FilesChanged(
                new FileEvent('file:///', FileChangeType::DELETED),
                new FileEvent('file:///', FileChangeType::DELETED),
                new FileEvent('file:///', FileChangeType::CREATED),
                new FileEvent('file:///', FileChangeType::CREATED),
            ),
            [
            ]
        ];
    }
}
