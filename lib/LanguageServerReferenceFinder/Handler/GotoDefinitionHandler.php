<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Handler;

use Amp\Promise;
use LanguageServerProtocol\Location;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\Range;
use LanguageServerProtocol\ServerCapabilities;
use LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;
use RuntimeException;

class GotoDefinitionHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var DefinitionLocator
     */
    private $definitionLocator;

    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var LocationConverter
     */
    private $locationConverter;

    public function __construct(Workspace $workspace, DefinitionLocator $definitionLocator, LocationConverter $locationConverter)
    {
        $this->definitionLocator = $definitionLocator;
        $this->workspace = $workspace;
        $this->locationConverter = $locationConverter;
    }

    public function methods(): array
    {
        return [
            'textDocument/definition' => 'definition',
        ];
    }

    public function definition(
        TextDocumentIdentifier $textDocument,
        Position $position
    ): Promise {
        return \Amp\call(function () use ($textDocument, $position) {
            $textDocument = $this->workspace->get($textDocument->uri);

            $offset = $position->toOffset($textDocument->text);

            try {
                $location = $this->definitionLocator->locateDefinition(
                    TextDocumentBuilder::create($textDocument->text)->uri($textDocument->uri)->language('php')->build(),
                    ByteOffset::fromInt($offset)
                );
            } catch (CouldNotLocateDefinition $couldNotLocateDefinition) {
                return null;
            }

            return $this->locationConverter->toLspLocation($location);
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->definitionProvider = true;
    }
}
