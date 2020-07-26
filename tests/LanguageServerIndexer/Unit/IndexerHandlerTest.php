<?php

namespace Phpactor\Extension\LanguageServerIndexer\Tests\Unit;

use Amp\CancellationTokenSource;
use Phpactor\AmpFsWatch\Exception\WatcherDied;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher\TestWatcher\TestWatcher;
use Phpactor\Extension\LanguageServerIndexer\Handler\IndexerHandler;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\LanguageServer\Adapter\DTL\DTLArgumentResolver;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Server\Transmitter\TestMessageTransmitter;
use Phpactor\LanguageServer\Core\Service\ServiceManager;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\Test\HandlerTester;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Psr\Log\LoggerInterface;
use Phpactor\Extension\LanguageServerIndexer\Tests\IntegrationTestCase;
use Psr\Log\NullLogger;
use function Amp\Promise\wait;
use function Amp\delay;

class IndexerHandlerTest extends IntegrationTestCase
{
    /**
     * @var LanguageServerTester
     */
    private $tester;

    protected function setUp(): void
    {
        $container = $this->container();
        $this->tester = $container->get(LanguageServerBuilder::class)->tester(
            ProtocolFactory::initializeParams($this->workspace()->path())
        );
    }

    public function testIndexer(): void
    {
        $this->workspace()->put(
            'Foobar.php',
            <<<'EOT'
<?php
EOT
        );

        $this->tester->initialize();
        wait(delay(10));

        self::assertGreaterThanOrEqual(2, $this->tester->transmitter()->count());
        self::assertStringContainsString('Indexing', $this->tester->transmitter()->shift()->params['message']);
        self::assertStringContainsString('Done indexing', $this->tester->transmitter()->shift()->params['message']);
    }

    public function testReindexNonStarted(): void
    {
        $this->tester->initialize();

        wait(delay(10));

        self::assertContains('indexer', $this->tester->servicesRunning());
        $this->tester->serviceStop('indexer');
        self::assertNotContains('indexer', $this->tester->servicesRunning());

        $this->tester->notifyAndWait('phpactor/indexer/reindex', []);

        self::assertContains('indexer', $this->tester->servicesRunning());
    }

    public function testReindexHard(): void
    {
        $this->tester->notifyAndWait('phpactor/indexer/reindex', [
            'soft' => false,
        ]);

        self::assertContains('indexer', $this->tester->servicesRunning());
    }

    public function testShowsMessageOnWatcherDied(): void
    {
        $this->markTestSkipped();

        $this->workspace()->put(
            'Foobar.php',
            <<<'EOT'
<?php
EOT
        );
        \Amp\Promise\wait(\Amp\call(function () {
            $indexer = $this->container()->get(Indexer::class);
            $watcher = new TestWatcher(new ModifiedFileQueue(), 0, new WatcherDied('No'));
            $handler = new IndexerHandler(
                $indexer,
                $watcher,
                $this->clientApi,
                $this->logger->reveal(),
                $this->serviceManager
            );
            $token = (new CancellationTokenSource())->getToken();
            yield $handler->indexer($token);
        }));

        $this->client->transmitter()->shift();
        $this->client->transmitter()->shift();

        self::assertStringContainsString('File watcher died:', $this->client->transmitter()->shift()->params['message']);
    }
}
