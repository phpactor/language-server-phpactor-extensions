<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Tests\Unit\Model;

use Closure;
use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerReferenceFinder\Model\WorkspaceSymbolProvider;
use Phpactor\Extension\LanguageServerReferenceFinder\Tests\IntegrationTestCase;
use Phpactor\Indexer\Adapter\Php\InMemory\InMemorySearchIndex;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\Indexer\Model\MemberReference;
use Phpactor\Indexer\Model\Record;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\Indexer\Model\Record\MemberRecord;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServerProtocol\SymbolInformation;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentLocator\ChainDocumentLocator;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use function Amp\Promise\wait;

class WorkspaceSymbolProviderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $this->workspace()->reset();
    }

    /**
     * @dataProvider provideProvide
     */
    public function testProvide(array $workspace, Closure $assertion, string $query): void
    {
        $container = $this->container();
        foreach ($workspace as $path => $contents) {
            $this->workspace()->put($path, $contents);
        }

        $indexer = $container->get(Indexer::class);
        assert($indexer instanceof Indexer);
        $indexer->getJob()->run();
        $client = $container->get(SearchClient::class);
        $locator = $container->get(TextDocumentLocator::class);

        $provider = new WorkspaceSymbolProvider($client, $locator);
        $informations = wait($provider->provideFor($query));
        $assertion($informations);
    }

    /**
     * @return Generator<mixed>
     */
    public function provideProvide(): Generator
    {
        yield 'No matches' => [
            [
                'Foo.php' => '<?php class Foo',
            ],
            function (array $infos) {
                self::assertCount(0, $infos);
            },
            'Nothing'
        ];

        yield 'Class' => [
            [
                'Foo1.php' => '<?php class Foo',
            ],
            function (array $infos) {
                self::assertCount(1, $infos);
                $info = reset($infos);
                assert($info instanceof SymbolInformation);
                self::assertEquals('Foo', $info->name);
            },
            'F'
        ];

        yield 'Methods not currently supported' => [
            [
                'Foo.php' => '<?php class Foo { function barbar() {} }',
            ],
            function (array $infos) {
                self::assertCount(0, $infos);
            },
            'bar'
        ];

        yield 'Functions' => [
            [
                'Foo.php' => '<?php function barbar(){}',
            ],
            function (array $infos) {
                self::assertCount(1, $infos);
            },
            'bar'
        ];

        yield 'Constants' => [
            [
                'Foo4.php' => '<?php define("Foobar", "barfoo");',
            ],
            function (array $infos) {
                self::assertCount(1, $infos);
            },
            'Foo'
        ];
    }
}
