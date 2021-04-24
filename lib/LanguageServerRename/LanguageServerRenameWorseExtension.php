<?php

namespace Phpactor\Extension\LanguageServerRename;

use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Phpactor\ClassMover\ClassMover;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\ClassToFile\ClassToFileExtension;
use Phpactor\Extension\LanguageServerReferenceFinder\Adapter\Indexer\WorkspaceUpdateReferenceFinder;
use Phpactor\Extension\LanguageServerRename\Adapter\ClassMover\FileRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\ClassMover\FileRenamer as PhpactorFileRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\ClassToFile\ClassToFileUriToNameConverter;
use Phpactor\Extension\LanguageServerRename\Listener\FileRenameListener;
use Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\ClassMover\ClassRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\MemberRenamer;
use Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\VariableRenamer;
use Phpactor\Extension\LanguageServerRename\Util\LocatedTextEditConverter;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\Indexer\Model\QueryClient;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\MapResolver\Resolver;
use Phpactor\ReferenceFinder\DefinitionAndReferenceFinder;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\WorseReferenceFinder\TolerantVariableReferenceFinder;

class LanguageServerRenameWorseExtension implements Extension
{
    public const TAG_RENAMER = 'language_server_rename.renamer';
    public const PARAM_FILE_RENAME = 'language_server_rename.file_rename';

    /**

     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container): void
    {
        $container->register(VariableRenamer::class, function (Container $container) {
            return new VariableRenamer(
                new TolerantVariableReferenceFinder(
                    $container->get('worse_reflection.tolerant_parser'),
                    true
                ),
                $container->get(TextDocumentLocator::class),
                $container->get('worse_reflection.tolerant_parser')
            );
        }, [
            LanguageServerRenameExtension::TAG_RENAMER => []
        ]);

        $container->register(MemberRenamer::class, function (Container $container) {
            return new MemberRenamer(
                $container->get(DefinitionAndReferenceFinder::class),
                $container->get(TextDocumentLocator::class),
                $container->get('worse_reflection.tolerant_parser')
            );
        }, [
            LanguageServerRenameExtension::TAG_RENAMER => []
        ]);

        $container->register(ClassRenamer::class, function (Container $container) {
            return new ClassRenamer(
                $container->get(DefinitionAndReferenceFinder::class),
                $container->get(TextDocumentLocator::class),
                $container->get('worse_reflection.tolerant_parser'),
                $container->get(ClassMover::class)
            );
        }, [
            LanguageServerRenameExtension::TAG_RENAMER => []
        ]);

        $container->register(DefinitionAndReferenceFinder::class, function (Container $container) {
            // wrap the definiton and reference finder to update the index with the current workspace
            return new WorkspaceUpdateReferenceFinder(
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(Indexer::class),
                new DefinitionAndReferenceFinder(
                    $container->get(ReferenceFinderExtension::SERVICE_DEFINITION_LOCATOR),
                    $container->get(ReferenceFinder::class)
                )
            );
        });

        $container->register(FileRenameListener::class, function (Container $container) {
            if ($container->getParameter(self::PARAM_FILE_RENAME)) {
                return new ListenerProviderAggregate();
            }

            return new FileRenameListener(
                $container->get(LocatedTextEditConverter::class),
                $container->get(ClientApi::class),
                $container->get(FileRenamer::class)
            );
        }, [
            LanguageServerExtension::TAG_LISTENER_PROVIDER => []
        ]);

        $container->register(FileRenamer::class, function (Container $container) {
            return new PhpactorFileRenamer(
                new ClassToFileUriToNameConverter($container->get(ClassToFileExtension::SERVICE_CONVERTER)),
                $container->get(TextDocumentLocator::class),
                $container->get(QueryClient::class),
                $container->get(ClassMover::class)
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema): void
    {
        $schema->setDefaults([
            self::PARAM_FILE_RENAME => false,
        ]);
        $schema->setDescriptions([
            self::PARAM_FILE_RENAME => '(experimental) Support for moving classes when a file move is detected'
        ]);
    }
}
