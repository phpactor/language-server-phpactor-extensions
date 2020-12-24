<?php

namespace Phpactor\Extension\LanguageServerRename\Handler;

use Amp\Promise;
use Phpactor\LanguageServerProtocol\PrepareRenameParams;
use Phpactor\LanguageServerProtocol\PrepareRenameRequest;
use Phpactor\LanguageServerProtocol\RenameOptions;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;

class RenameHandler implements Handler, CanRegisterCapabilities
{
    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        dump("Registering methods");
        return [
            PrepareRenameRequest::METHOD => 'prepareRename',
        ];
    }
    
    public function prepareRename(PrepareRenameParams $params): Promise
    {
        // https://microsoft.github.io/language-server-protocol/specification#textDocument_prepareRename
        return \Amp\call(function () use ($params) {
            $position = $params->position;
            return null;
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        dump("Registering capabilities");
        $capabilities->renameProvider = new RenameOptions(true);
    }
}
