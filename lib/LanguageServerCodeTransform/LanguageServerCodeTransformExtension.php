<?php

namespace Phpactor\Extension\LanguageServerCodeTransform;

use Phpactor\CodeTransform\Domain\Refactor\ImportClass;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportClassCommand;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\MapResolver\Resolver;

class LanguageServerCodeTransformExtension implements Extension
{
    public const COMMAND_IMPORT_CLASS = 'phpactor.action.import_class';

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $container->register(ImportClassCommand::class, function (Container $container) {
            return new ImportClassCommand(
                $container->get(ImportClass::class),
                $container->get(Workspace::class),
                $container->get(TextEditConverter::class),
                $container->get(ClientApi::class)
            );
        }, [
            LanguageServerExtension::TAG_COMMAND => [
                'name' => self::COMMAND_IMPORT_CLASS
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
    }
}
