<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Handler;

use Amp\Promise;
use Amp\Success;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerReferenceFinder\Model\Highlighter;
use Phpactor\LanguageServerProtocol\ClientCapabilities;
use Phpactor\LanguageServerProtocol\DocumentHighlight;
use Phpactor\LanguageServerProtocol\DocumentHighlightOptions;
use Phpactor\LanguageServerProtocol\DocumentHighlightParams;
use Phpactor\LanguageServerProtocol\DocumentHighlightRequest;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;

class HighlightHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var Highlighter
     */
    private $highlighter;

    /**
     * @var ClientCapabilities
     */
    private ClientCapabilities $clientCapabilities;

    public function __construct(Workspace $workspace, Highlighter $highlighter, ClientCapabilities $clientCapabilities)
    {
        $this->workspace = $workspace;
        $this->highlighter = $highlighter;
        $this->clientCapabilities = $clientCapabilities;
    }

    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            DocumentHighlightRequest::METHOD => 'highlight',
        ];
    }

    /**
     * @return Promise<array<DocumentHighlight>|null>
     */
    public function highlight(DocumentHighlightParams $params): Promise
    {
        $textDocument = $this->workspace->get($params->textDocument->uri);
        $offset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);

        return new Success($this->highlighter->highlightsFor($textDocument->text, $offset)->toArray());
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        if (null === $this->clientCapabilities->textDocument->documentHighlight) {
            return;
        }

        $options = new DocumentHighlightOptions();
        $capabilities->documentHighlightProvider = $options;
    }
}
