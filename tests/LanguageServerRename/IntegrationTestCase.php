<?php

namespace Phpactor\Extension\LanguageServerRename\Tests;

use Phpactor\Extension\LanguageServerBridge\LanguageServerBridgeExtension;
use Phpactor\Extension\LanguageServerIndexer\LanguageServerIndexerExtension;
use Phpactor\Extension\LanguageServerRename\LanguageServerRenameExtension;
use Phpactor\Extension\LanguageServerRename\Tests\Extension\TestExtension;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\ReferenceFinder\ReferenceFinderExtension;
use Phpactor\Extension\ComposerAutoloader\ComposerAutoloaderExtension;
use Phpactor\Extension\ClassToFile\ClassToFileExtension;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
use Phpactor\Extension\SourceCodeFilesystem\SourceCodeFilesystemExtension;
use Phpactor\Extension\Logger\LoggingExtension;
use Phpactor\FilePathResolverExtension\FilePathResolverExtension;
use Phpactor\Extension\Console\ConsoleExtension;
use Phpactor\Container\PhpactorContainer;
use Phpactor\Indexer\Extension\IndexerExtension;
use Phpactor\Container\Container;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TestUtils\Workspace;

class IntegrationTestCase extends TestCase
{
    protected function workspace(): Workspace
    {
        return Workspace::create(__DIR__ . '/Workspace');
    }

    protected function container(array $config = []): Container
    {
        $container = PhpactorContainer::fromExtensions([
            LanguageServerExtension::class,
            TestExtension::class,
            LanguageServerRenameExtension::class,
            FilePathResolverExtension::class,
            LanguageServerBridgeExtension::class,
            LoggingExtension::class,
        ], array_merge([
        ], $config));

        return $container;
    }
}
