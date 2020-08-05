<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Handler;

use Amp\Promise;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\CodeActionParams;
use Phpactor\LanguageServerProtocol\CodeActionRequest;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use function Amp\call;

class CodeActionHandler implements Handler, CanRegisterCapabilities
{
    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            CodeActionRequest::METHOD => 'codeAction'
        ];
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
    }

    /**
     * @return Promise<array<CodeAction>>
     */
    public function codeAction(CodeActionParams $params): Promise
    {
        return call(function () use ($params) {
        });
    }
}
