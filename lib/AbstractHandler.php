<?php

namespace Phpactor\Extension;

use Phpactor\LanguageServerProtocol\ClientCapabilities;

abstract class AbstractHandler
{
    /**
     * @var ClientCapabilities
     */
    protected $clientCapabilities;

    public function __construct(ClientCapabilities $clientCapabilities)
    {
        $this->clientCapabilities = $clientCapabilities;
    }
}
