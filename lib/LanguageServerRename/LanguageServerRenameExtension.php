<?php

namespace Phpactor\Extension\LanguageServerRename;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerRename\Bridge\ChainRenamer;
use Phpactor\Extension\LanguageServerRename\Bridge\MemberRenamer;
use Phpactor\Extension\LanguageServerRename\Bridge\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Handler\RenameHandler;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\TextDocument\TextDocumentLocator;

class LanguageServerRenameExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container): void
    {
        $container->register(Renamer::class, function (Container $container) {
            return new ChainRenamer(
                [
                    new VariableRenamer(),
                    new MemberRenamer(),
                ]
            );
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
