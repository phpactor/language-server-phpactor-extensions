<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\Model;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImportCandidateProvider;
use Phpactor\Indexer\Adapter\Php\InMemory\InMemorySearchIndex;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TextDocument\ByteOffset;

class NameImportCandidateProviderTest extends TestCase
{
    /**
     * @dataProvider provideProvide
     */
    public function testProvide(string $source, array $availableNames, int $expectedCount): void
    {
        [ $source, $offset ] = ExtractOffset::fromSource($source);

        $client = new InMemorySearchIndex();
        foreach ($availableNames as $candidate) {
            $client->write(ClassRecord::fromName($candidate));
        }

        self::assertCount(
            $expectedCount,
            iterator_to_array((new NameImportCandidateProvider($client, new Parser()))->provide($source, ByteOffset::fromInt($offset)))
        );
    }

    /**
     * @return Generator<mixed>
     */
    public function provideProvide(): Generator
    {
        yield 'none' => [
            '<?php <>',
            [],
            0
        ];

        yield 'name not found in root namespace' => [
            '<?php Na<>me',
            [
            ],
            0
        ];

        yield 'name in root namespace' => [
            '<?php Na<>me',
            [
                'Name',
            ],
            1
        ];

        yield 'qualified names are not allowed' => [
            '<?php Foobar\Na<>me',
            [
                'Foobar\Name',
            ],
            0
        ];

        yield 'namespaced name found' => [
            '<?php namespace Bar { Na<>me }',
            [
                'Foobar\Name',
            ],
            1
        ];
    }
}
