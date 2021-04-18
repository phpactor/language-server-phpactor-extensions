<?php

namespace Phpactor\Extension\LanguageServerIndexer;

use Phpactor\AmpFsWatch\Watcher;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerIndexer\Handler\IndexerHandler;
use Phpactor\Extension\LanguageServerIndexer\Handler\WorkspaceSymbolHandler;
use Phpactor\Extension\LanguageServerIndexer\Listener\ReindexListener;
use Phpactor\Extension\LanguageServerIndexer\Model\WorkspaceSymbolProvider;
use Phpactor\Extension\LanguageServerIndexer\Watcher\LanguageServerWatcher;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\Logger\LoggingExtension;
use Phpactor\Indexer\Extension\IndexerExtension;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Service\ServiceManager;
use Phpactor\MapResolver\Resolver;
use Phpactor\TextDocument\TextDocumentLocator;
use Psr\EventDispatcher\EventDispatcherInterface;

class LanguageServerIndexerExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container): void
    {
        $this->registerSessionHandler($container);

        $container->register(WorkspaceSymbolHandler::class, function (Container $container) {
            return new WorkspaceSymbolHandler(
                new WorkspaceSymbolProvider($container->get(SearchClient::class), $container->get(TextDocumentLocator::class))
            );
        }, [ LanguageServerExtension::TAG_METHOD_HANDLER => [] ]);
    }

    public function configure(Resolver $schema): void
    {
    }

    private function registerSessionHandler(ContainerBuilder $container): void
    {
        $container->register(IndexerHandler::class, function (Container $container) {
            return new IndexerHandler(
                $container->get(Indexer::class),
                $container->get(Watcher::class),
                $container->get(ClientApi::class),
                $container->get(LoggingExtension::SERVICE_LOGGER),
                $container->get(EventDispatcherInterface::class)
            );
        }, [
            LanguageServerExtension::TAG_METHOD_HANDLER => [],
            LanguageServerExtension::TAG_SERVICE_PROVIDER => []
        ]);

        $container->register(ReindexListener::class, function (Container $container) {
            return new ReindexListener($container->get(ServiceManager::class));
        }, [
            LanguageServerExtension::TAG_LISTENER_PROVIDER => [],
        ]);
    }
}
