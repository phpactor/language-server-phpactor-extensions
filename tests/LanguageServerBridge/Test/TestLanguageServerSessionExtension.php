<?php

namespace Phpactor\Extension\LanguageServerBridge\Tests\Test;

use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServer\LanguageServerSessionExtension;
use Phpactor\LanguageServer\Adapter\DTL\DTLArgumentResolver;
use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\LanguageServer\Core\Server\SessionServices;
use Phpactor\LanguageServer\Core\Server\Transmitter\TestMessageTransmitter;
use Phpactor\LanguageServer\Core\Service\ServiceManager;
use Phpactor\MapResolver\Resolver;
use Psr\Log\NullLogger;

class TestLanguageServerSessionExtension implements Extension
{
    /**
     * @var LanguageServerSessionExtension
     */
    private $sessionExtension;

    public function __construct()
    {
        $transmitter = new TestMessageTransmitter();
        $this->sessionExtension = new LanguageServerSessionExtension(
            $transmitter,
        );
    }
    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container): void
    {
        $this->sessionExtension->load($container);
    }

    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema): void
    {
        $this->sessionExtension->configure($schema);
    }
}
