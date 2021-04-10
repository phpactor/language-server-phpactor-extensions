<?php

namespace Phpactor\Extension\LanguageServerIndexer\Handler;

use Amp\Promise;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerIndexer\Model\WorkspaceSymbolProvider;
use Phpactor\Indexer\Model\Query\Criteria;
use Phpactor\Indexer\Model\Record;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\LanguageServerProtocol\SymbolInformation;
use Phpactor\LanguageServerProtocol\WorkspaceSymbolParams;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\TextDocumentBuilder;

class WorkspaceSymbolHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var WorkspaceSymbolProvider
     */
    private $provider;

    public function __construct(WorkspaceSymbolProvider $provider)
    {
        $this->provider = $provider;
    }

    public function methods(): array
    {
        return [
            'workspace/symbol' => 'symbol',
        ];
    }

    /**
     * @return Promise<SymbolInformation[]>
     */
    public function symbol(
        WorkspaceSymbolParams $params
    ): Promise {
        return \Amp\call(function () use ($params) {
            return $this->provider->provideFor($params->query);
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->workspaceSymbolProvider = true;
    }
}

