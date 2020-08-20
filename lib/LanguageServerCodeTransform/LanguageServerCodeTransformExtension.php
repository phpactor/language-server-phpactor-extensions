<?php

namespace Phpactor\Extension\LanguageServerCodeTransform;

use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\ImportNameProvider;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\TransformerCodeActionPovider;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\TransformCommand;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\MapResolver\Resolver;

class LanguageServerCodeTransformExtension implements Extension
{
    public const PARAM_IMPORT_GLOBALS = 'language_server_code_transform.import_globals';

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

        $container->register(TransformCommand::class, function (Container $container) {
            return new TransformCommand(
                $container->get(ClientApi::class),
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get('code_transform.transformers')
            );
        }, [
            LanguageServerExtension::TAG_COMMAND => [
                'name' => TransformCommand::NAME
            ],
        ]);
    }

    private function registerCodeActions(ContainerBuilder $container): void
    {
        $container->register(ImportNameProvider::class, function (Container $container) {
            return new ImportNameProvider(
                $container->get(UnresolvableClassNameFinder::class),
                $container->get(SearchClient::class),
                $container->getParameter(self::PARAM_IMPORT_GLOBALS)
            );
        }, [
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => [],
            LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => []
        ]);

        $container->register(TransformerCodeActionPovider::class.'complete_constructor', function (Container $container) {
            return new TransformerCodeActionPovider(
                $container->get('code_transform.transformers'),
                'complete_constructor',
                'Complete Constructor'
            );
        }, [
            LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => [],
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => []
        ]);

        $container->register(TransformerCodeActionPovider::class.'add_missing_properties', function (Container $container) {
            return new TransformerCodeActionPovider(
                $container->get('code_transform.transformers'),
                'add_missing_properties',
                'Add missing properties'
            );
        }, [
            LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => [],
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => []
        ]);

        $container->register(TransformerCodeActionPovider::class.'implement_contracts', function (Container $container) {
            return new TransformerCodeActionPovider(
                $container->get('code_transform.transformers'),
                'implement_contracts',
                'Implement contracts'
            );
        }, [
            LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => [],
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => []
        ]);

        $container->register(TransformerCodeActionPovider::class.'fix_namespace_class_name', function (Container $container) {
            return new TransformerCodeActionPovider(
                $container->get('code_transform.transformers'),
                'fix_namespace_class_name',
                'Fix PSR namespace and class name'
            );
        }, [
            LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => [],
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => []
        ]);
    }
}
