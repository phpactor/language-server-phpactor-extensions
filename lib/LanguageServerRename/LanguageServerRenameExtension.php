<?php

namespace Phpactor\Extension\LanguageServerRename;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerRename\Adapter\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Adapter\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\ChainRenamer;
use Phpactor\Extension\LanguageServerRename\Handler\RenameHandler;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\TextDocumentLocator;

class LanguageServerRenameExtension implements Extension
{
    public const TAG_RENAMER = 'language_server_rename.renamer';

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container): void
    {
        $container->register(RenameLocationsProvider::class, function (Container $container) {
            new RenameLocationsProvider(
                $container->get(ReferenceFinder::class),
                $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR),
            );
        });
        $container->register(VariableRenamer::class, function (Container $container) {
            return new VariableRenamer(
                $container->get(RenameLocationsProvider::class)
            );
        }, [ LanguageServerRenameExtension::TAG_RENAMER => [] ]);
        $container->register(Renamer::class, function (Container $container) {
            return new ChainRenamer(array_map(function (string $serviceId) use ($container) {
                return $container->get($serviceId);
            }, array_keys($container->getServiceIdsForTag(self::TAG_RENAMER))));
        });

        $container->register(RenameHandler::class, function (Container $container) {
            return new RenameHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(TextDocumentLocator::class),
                $container->get(Renamer::class),
            );
        }, [ LanguageServerExtension::TAG_METHOD_HANDLER => [] ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema): void
    {
    }
}
