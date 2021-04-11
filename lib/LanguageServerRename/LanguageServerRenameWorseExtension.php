<?php

namespace Phpactor\Extension\LanguageServerRename;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\MemberRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Adapter\Worse\VariableRenamer;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use Phpactor\MapResolver\Resolver;
use Phpactor\ReferenceFinder\DefinitionAndReferenceFinder;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\TextDocumentLocator;

class LanguageServerRenameWorseExtension implements Extension
{
    public const TAG_RENAMER = 'language_server_rename.renamer';
    /**

     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container): void
    {
        $container->register(RenameLocationsProvider::class, function (Container $container) {
            return new RenameLocationsProvider(
                $container->get('worse_reference_finder.reference_finder.variable'),
                $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR)
            );
        });

        $container->register(VariableRenamer::class, function (Container $container) {
            return new VariableRenamer(
                $container->get(RenameLocationsProvider::class),
                $container->get(TextDocumentLocator::class),
                $container->get('worse_reflection.tolerant_parser')
            );
        }, [
            LanguageServerRenameExtension::TAG_RENAMER => []
        ]);

        $container->register(MemberRenamer::class, function (Container $container) {
            return new MemberRenamer(
                new DefinitionAndReferenceFinder(
                    $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR),
                    $container->get(ReferenceFinder::class)
                ),
                $container->get(TextDocumentLocator::class),
                $container->get('worse_reflection.tolerant_parser')
            );
        }, [
            LanguageServerRenameExtension::TAG_RENAMER => []
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema): void
    {
    }
}
