<?php

namespace Phpactor\Extension;

use Phpactor\Container\Container;
use Phpactor\LanguageServerProtocol\ClientCapabilities;

abstract class AbstractExtension
{
    protected function clientCapabilities(Container $container): ClientCapabilities
    {
        return $container->get(ClientCapabilities::class);
    }
}
