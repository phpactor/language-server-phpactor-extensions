<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Handler;

use Amp\Promise;
use LanguageServerProtocol\Location as LspLocation;
use LanguageServerProtocol\Range;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\ReferenceContext;
use LanguageServerProtocol\ServerCapabilities;
use LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;

class ReferencesHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var ReferenceFinder
     */
    private $finder;

    /**
     * @var DefinitionLocator
     */
    private $definitionLocator;

    public function __construct(Workspace $workspace, ReferenceFinder $finder, DefinitionLocator $definitionLocator)
    {
        $this->workspace = $workspace;
        $this->finder = $finder;
        $this->definitionLocator = $definitionLocator;
    }

    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            'textDocument/references' => 'references',
        ];
    }

    public function references(
        TextDocumentIdentifier $textDocument,
        Position $position,
        ReferenceContext $context
    ): Promise {
        return \Amp\call(function () use ($textDocument, $position, $context) {
            $textDocument = $this->workspace->get($textDocument->uri);
            $phpactorDocument = TextDocumentBuilder::create(
                $textDocument->text
            )->uri(
                $textDocument->uri
            )->language(
                $textDocument->languageId ?? 'php'
            )->build();

            $offset = ByteOffset::fromInt($position->toOffset($textDocument->text));
            $locations = new Locations([]);

            if ($context->includeDeclaration) {
                try {
                    $location = $this->definitionLocator->locateDefinition($phpactorDocument, $offset);
                    $locations = $locations->append(new Locations([
                        new Location($location->uri(), $location->offset())
                    ]));
                } catch (CouldNotLocateDefinition $notFound) {
                }
            }

            $locations = $locations->append($this->finder->findReferences($phpactorDocument, $offset));

            return $this->toLocations($locations);
        });
    }

    /**
     * @return LspLocation[]
     */
    private function toLocations(Locations $locations): array
    {
        $lspLocations = [];
        foreach ($locations as $location) {
            assert($location instanceof Location);

            $contents = @file_get_contents($location->uri());

            if (false === $contents) {
                continue;
            }

            $startPosition = OffsetHelper::offsetToPosition($contents, $location->offset()->toInt());
            $lspLocations[] = new LspLocation($location->uri()->__toString(), new Range(
                $startPosition,
                $startPosition
            ));
        }

        return $lspLocations;
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->referencesProvider = true;
    }
}
