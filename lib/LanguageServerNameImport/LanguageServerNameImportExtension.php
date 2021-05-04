<?php

namespace Phpactor\Extension\LanguageServerNameImport;

use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\LanguageServerNameImport\Service\NameImport;
use Phpactor\MapResolver\Resolver;

class LanguageServerNameImportExtension implements Extension
{
    public function load(ContainerBuilder $container): void
    {
        $container->register(
            NameImport::class,
            function (Container $container) {
                return new NameImport(
                    $container->get(ImportName::class),
                    $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE)
                );
            }
        );
    }

    public function configure(Resolver $schema): void
    {
    }
}
