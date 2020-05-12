<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Handler;

use Amp\Promise;
use LanguageServerProtocol\Location as LspLocation;
use LanguageServerProtocol\Range;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\ServerCapabilities;
use LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\ReferenceFinder\ClassImplementationFinder;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;

class GotoImplementationHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var ClassImplementationFinder
     */
    private $finder;

    /**
     * @var LocationConverter
     */
    private $locationConverter;

    public function __construct(Workspace $workspace, ClassImplementationFinder $finder, LocationConverter $locationConverter)
    {
        $this->workspace = $workspace;
        $this->finder = $finder;
        $this->locationConverter = $locationConverter;
    }

    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            'textDocument/implementation' => 'gotoImplementation',
        ];
    }

    public function gotoImplementation(
        TextDocumentIdentifier $textDocument,
        Position $position
    ): Promise {
        return \Amp\call(function () use ($textDocument, $position) {
            $textDocument = $this->workspace->get($textDocument->uri);
            $phpactorDocument = TextDocumentBuilder::create(
                $textDocument->text
            )->uri(
                $textDocument->uri
            )->language(
                $textDocument->languageId ?? 'php'
            )->build();

            $offset = ByteOffset::fromInt($position->toOffset($textDocument->text));
            $locations = $this->finder->findImplementations($phpactorDocument, $offset);

            return $this->locationConverter->toLspLocations($locations);
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->implementationProvider = true;
    }
}
