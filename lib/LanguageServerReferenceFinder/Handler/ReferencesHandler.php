<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Handler;

use Amp\Delayed;
use Amp\Promise;
use LanguageServerProtocol\Location as LspLocation;
use LanguageServerProtocol\MessageType;
use LanguageServerProtocol\Range;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\ReferenceContext;
use LanguageServerProtocol\ServerCapabilities;
use LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\Extension\LanguageServerReferenceFinder\LanguageServerReferenceFinderExtension;
use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Server\ServerClient;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\Location;
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

    /**
     * @var float
     */
    private $timeoutSeconds;

    public function __construct(
        Workspace $workspace,
        ReferenceFinder $finder,
        DefinitionLocator $definitionLocator,
        float $timeoutSeconds = 5.0
    ) {
        $this->workspace = $workspace;
        $this->finder = $finder;
        $this->definitionLocator = $definitionLocator;
        $this->timeoutSeconds = $timeoutSeconds;
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
        ReferenceContext $context,
        ServerClient $client
    ): Promise {
        return \Amp\call(function () use ($textDocument, $position, $context, $client) {
            $textDocument = $this->workspace->get($textDocument->uri);
            $phpactorDocument = TextDocumentBuilder::create(
                $textDocument->text
            )->uri(
                $textDocument->uri
            )->language(
                $textDocument->languageId ?? 'php'
            )->build();

            $offset = ByteOffset::fromInt($position->toOffset($textDocument->text));

            $locations = [];
            if ($context->includeDeclaration) {
                try {
                    $potentialLocation = $this->definitionLocator->locateDefinition($phpactorDocument, $offset);
                    $locations[] = new Location($potentialLocation->uri(), $potentialLocation->offset());
                } catch (CouldNotLocateDefinition $notFound) {
                }
            }

            $start = microtime(true);
            $count = 0;
            foreach ($this->finder->findReferences($phpactorDocument, $offset) as $potentialLocation) {
                if ($potentialLocation->isSurely()) {
                    $locations[] = $potentialLocation->location();
                }

                if ($count++ % 100 === 0 && $count > 0) {
                    $client->notification('window/showMessage', [
                        'type' => MessageType::INFO,
                        'message' => sprintf(
                            '... scanned %s references confirmed %s ...',
                            $count - 1,
                            count($locations)
                        )
                    ]);
                }

                if (microtime(true) - $start > $this->timeoutSeconds) {
                    $client->notification('window/showMessage', [
                        'type' => MessageType::WARNING,
                        'message' => sprintf(
                            'Reference find stopped, %s/%s references confirmed but took too long (%s/%s seconds). Adjust `%s`',
                            count($locations),
                            $count,
                            number_format(microtime(true) - $start, 2),
                            $this->timeoutSeconds,
                            LanguageServerReferenceFinderExtension::PARAM_REFERENCE_TIMEOUT
                        )
                    ]);
                    return $this->toLocations($locations);
                }

                if ($count++ % 10) {
                    // give other co-routines a chance
                    yield new Delayed(0);
                }
            }

            $client->notification('window/showMessage', [
                'type' => MessageType::INFO,
                'message' => sprintf(
                    'Found %s reference(s) from %s candidates',
                    count($locations),
                    $count
                )
            ]);

            return $this->toLocations($locations);
        });
    }

    /**
     * @param Location[] $locations
     * @return LspLocation[]
     */
    private function toLocations(array $locations): array
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
