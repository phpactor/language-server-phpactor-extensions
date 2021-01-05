<?php

namespace Phpactor\Extension\LanguageServerCodeTransform;

use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\ClassToFile\ClassToFileExtension;
use Phpactor\Extension\CodeTransform\CodeTransformExtension;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\CreateClassProvider;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\ImportNameProvider;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\TransformerCodeActionPovider;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\CreateClassCommand;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\TransformCommand;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
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

        $container->register(CreateClassCommand::class, function (Container $container) {
            return new CreateClassCommand(
                $container->get(ClientApi::class),
                $container->get(LanguageServerExtension::SERVICE_SESSION_WORKSPACE),
                $container->get(CodeTransformExtension::SERVICE_CLASS_GENERATORS),
                $container->get(ClassToFileExtension::SERVICE_CONVERTER)
            );
        }, [
            LanguageServerExtension::TAG_COMMAND => [
                'name' => CreateClassCommand::NAME
            ],
        ]);
    }

    private function registerCodeActions(ContainerBuilder $container): void
    {
        $container->register(ImportNameProvider::class, function (Container $container) {
            return new ImportNameProvider(
                $container->get(UnresolvableClassNameFinder::class),
                $container->get(WorseReflectionExtension::SERVICE_REFLECTOR),
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
            // complete constructor diagnostics (and the subsequent action) are
            // not very accurate. better to disable it than show false
            // positives all the time, the code action is still available
            //
            // LanguageServerExtension::TAG_DIAGNOSTICS_PROVIDER => [],
            LanguageServerExtension::TAG_CODE_ACTION_PROVIDER => []
        ]);

        $container->register(CreateClassProvider::class, function (Container $container) {
            return new CreateClassProvider(
                $container->get(CodeTransformExtension::SERVICE_CLASS_GENERATORS),
                $container->get('worse_reflection.tolerant_parser')
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
