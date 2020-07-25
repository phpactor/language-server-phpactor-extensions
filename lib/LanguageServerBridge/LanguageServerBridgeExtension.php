<?php

namespace Phpactor\Extension\LanguageServerBridge;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\MapResolver\Resolver;

class LanguageServerBridgeExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $container->register(LocationConverter::class, function (Container $container) {
            return new LocationConverter($container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE));
        });

        $container->register(TextEditConverter::class, function (Container $container) {
            return new TextEditConverter($container->get(LocationConverter::class));
        });
    }
}
