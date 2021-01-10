<?php

namespace Phpactor\Extension\LanguageServerRename\Handler;

use Amp\Promise;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\LanguageServerProtocol\PrepareRenameParams;
use Phpactor\LanguageServerProtocol\PrepareRenameRequest;
use Phpactor\LanguageServerProtocol\RenameOptions;
use Phpactor\LanguageServerProtocol\RenameParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\RenameRequest;

class RenameHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var Renamer
     */
    private $renamer;


    public function __construct(Workspace $workspace, Renamer $renamer)
    {
        $this->workspace = $workspace;
        $this->renamer = $renamer;
    }
    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            PrepareRenameRequest::METHOD => 'prepareRename',
            RenameRequest::METHOD => 'rename',
        ];
    }

    public function rename(RenameParams $params): Promise
    {
        return \Amp\call(function () use ($params) {
            return $this->renamer->rename($this->workspace->get($params->textDocument->uri), $params->position, $params->newName);
        });
    }
    
    public function prepareRename(PrepareRenameParams $params): Promise
    {
        // https://microsoft.github.io/language-server-protocol/specification#textDocument_prepareRename
        return \Amp\call(function () use ($params) {
            return $this->renamer->prepareRename($this->workspace->get($params->textDocument->uri), $params->position);
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->renameProvider = new RenameOptions(true);
    }
}
