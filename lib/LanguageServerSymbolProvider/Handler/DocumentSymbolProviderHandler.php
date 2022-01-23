<?php

namespace Phpactor\Extension\LanguageServerSymbolProvider\Handler;

use Amp\Promise;
use Amp\Success;
use Phpactor\Extension\LanguageServerSymbolProvider\Model\DocumentSymbolProvider;
use Phpactor\LanguageServerProtocol\ClientCapabilities;
use Phpactor\LanguageServerProtocol\DocumentSymbolOptions;
use Phpactor\LanguageServerProtocol\DocumentSymbolParams;
use Phpactor\LanguageServerProtocol\DocumentSymbolRequest;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;

class DocumentSymbolProviderHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var DocumentSymbolProvider
     */
    private $provider;

    /**
     * @var ClientCapabilities
     */
    private ClientCapabilities $clientCapabilities;

    public function __construct(
        Workspace $workspace,
        DocumentSymbolProvider $provider,
        ClientCapabilities $clientCapabilities,
    ) {
        $this->workspace = $workspace;
        $this->provider = $provider;
        $this->clientCapabilities = $clientCapabilities;
    }

    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            DocumentSymbolRequest::METHOD => 'documentSymbols',
        ];
    }

    /**
     * @return Promise<array>
     */
    public function documentSymbols(DocumentSymbolParams $params): Promise
    {
        $textDocument = $this->workspace->get($params->textDocument->uri);

        return new Success($this->provider->provideFor($textDocument->text));
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        if (null === $this->clientCapabilities->textDocument->documentSymbol) {
            return;
        }

        $capabilities->documentSymbolProvider = new DocumentSymbolOptions();
    }
}
