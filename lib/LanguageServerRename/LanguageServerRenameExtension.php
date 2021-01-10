<?php

namespace Phpactor\Extension\LanguageServerRename;

use Microsoft\PhpParser\Parser;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerRename\Handler\RenameHandler;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\MapResolver\Resolver;
use Phpactor\ReferenceFinder\ReferenceFinder;

class LanguageServerRenameExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $container->register(Renamer::class, function (Container $container) {
            return new Renamer(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get('worse_reflection.tolerant_parser'),
                $container->get(ReferenceFinder::class),
                $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR),
                $container->get(ClientApi::class),
                new NodeUtils(),
            );
        });

        $container->register(RenameHandler::class, function (Container $container) {
            return new RenameHandler(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(Renamer::class)
            );
        }, [ LanguageServerExtension::TAG_METHOD_HANDLER => [] ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
    }
}
