<?php

namespace Phpactor\Extension\LanguageServerCodeTransform;

use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\ImportClassProvider;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\MapResolver\Resolver;

class LanguageServerCodeTransformExtension implements Extension
{
    const PARAM_IMPORT_GLOBALS = 'language_server_code_transform.import_globals';

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $this->registerCommands($container);
        $this->registerCodeActions($container);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
        $schema->setDefaults([
            self::PARAM_IMPORT_GLOBALS => false,
        ]);
        $schema->setDescriptions([
            self::PARAM_IMPORT_GLOBALS => 'Show hints for non-imported global classes and functions'
        ]);
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        $container->register(ImportNameCommand::class, function (Container $container) {
            return new ImportNameCommand(
                $container->get(ImportName::class),
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(TextEditConverter::class),
                $container->get(ClientApi::class)
            );
        }, [
            LanguageServerExtension::TAG_COMMAND => [
                'name' => ImportNameCommand::NAME
            ],
        ]);
    }

    private function registerCodeActions(ContainerBuilder $container): void
    {
        $container->register(ImportClassProvider::class, function (Container $container) {
            return new ImportClassProvider(
                $container->get(UnresolvableClassNameFinder::class),
                $container->get(SearchClient::class),
                $container->getParameter(self::PARAM_IMPORT_GLOBALS)
            );
        }, [
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => [],
            LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => []
        ]);
    }
}
