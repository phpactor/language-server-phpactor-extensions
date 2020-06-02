<?php

namespace Phpactor\Extension\LanguageServerIndexer\Tests\Unit;

use Amp\CancellationTokenSource;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher\TestWatcher\TestWatcher;
use Phpactor\Extension\LanguageServerIndexer\Handler\IndexerHandler;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Service\ServiceManager;
use Phpactor\LanguageServer\Test\HandlerTester;
use Psr\Log\LoggerInterface;
use Phpactor\Extension\LanguageServerIndexer\Tests\IntegrationTestCase;

class IndexerHandlerTest extends IntegrationTestCase
{
    /**
     * @var ObjectProphecy|LoggerInterface
     */
    private $logger;

    /**
     * @var ObjectProphecy
     */
    private $serviceManager;

    /**
     * @var TestRpcClient
     */
    private $client;

    /**
     * @var ClientApi
     */
    private $clientApi;

    protected function setUp(): void
    {
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->serviceManager = $this->prophesize(ServiceManager::class);
        $this->client = TestRpcClient::create();
        $this->clientApi = new ClientApi($this->client);
    }

    public function testIndexer(): void
    {
        $this->workspace()->put(
            'Foobar.php',
            <<<'EOT'
<?php
EOT
        );
        \Amp\Promise\wait(\Amp\call(function () {
            $indexer = $this->container()->get(Indexer::class);
            $watcher = new TestWatcher(new ModifiedFileQueue([
                new ModifiedFile($this->workspace()->path('Foobar.php'), ModifiedFile::TYPE_FILE),
            ]));
            $handler = new IndexerHandler($indexer, $watcher, $this->clientApi, $this->logger->reveal());
            $token = (new CancellationTokenSource())->getToken();
            yield $handler->indexer($token);
        }));

        $this->logger->debug(sprintf(
            'Indexed file: %s',
            $this->workspace()->path('Foobar.php')
        ))->shouldHaveBeenCalled();

        self::assertStringContainsString('Indexing', $this->client->transmitter()->shift()->params['message']);
        self::assertStringContainsString('Done indexing', $this->client->transmitter()->shift()->params['message']);
    }

    public function testReindexNonStarted(): void
    {
        $indexer = $this->container()->get(Indexer::class);
        $watcher = new TestWatcher(new ModifiedFileQueue());
        $handlerTester = new HandlerTester(
            new IndexerHandler($indexer, $watcher, $this->clientApi, $this->logger->reveal())
        );

        self::assertFalse($handlerTester->serviceManager()->isRunning(IndexerHandler::SERVICE_INDEXER));

        $handlerTester->dispatchAndWait('indexer/reindex', []);

        self::assertTrue($handlerTester->serviceManager()->isRunning(IndexerHandler::SERVICE_INDEXER));
    }

    public function testReindexHard(): void
    {
        $indexer = $this->container()->get(Indexer::class);
        $watcher = new TestWatcher(new ModifiedFileQueue());
        $handlerTester = new HandlerTester(
            new IndexerHandler($indexer, $watcher, $this->clientApi, $this->logger->reveal())
        );

        $handlerTester->serviceManager()->start(IndexerHandler::SERVICE_INDEXER);

        $handlerTester->dispatchAndWait('indexer/reindex', [
            'soft' => false,
        ]);

        self::assertTrue($handlerTester->serviceManager()->isRunning(IndexerHandler::SERVICE_INDEXER));
    }
}
