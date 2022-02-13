<?php

namespace Phpactor\Extension\LanguageServerCompletion\Tests\Unit\Handler;

use Phpactor\LanguageServerProtocol\ClientCapabilities;
use Phpactor\LanguageServerProtocol\SignatureHelp as LspSignatureHelp;
use Phpactor\LanguageServerProtocol\SignatureHelpClientCapabilities;
use Phpactor\LanguageServerProtocol\TextDocumentClientCapabilities;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use PHPUnit\Framework\TestCase;
use Phpactor\Completion\Core\SignatureHelp;
use Phpactor\Completion\Core\SignatureHelper;
use Phpactor\Extension\LanguageServerCompletion\Handler\SignatureHelpHandler;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;

class SignatureHelpHandlerTest extends TestCase
{
    const IDENTIFIER = '/test';

    public function testHandleHelpers(): void
    {
        $tester = $this->create([]);
        $tester->textDocument()->open(self::IDENTIFIER, 'hello');
        $response = $tester->requestAndWait(
            'textDocument/signatureHelp',
            [
                'textDocument' => new TextDocumentIdentifier(self::IDENTIFIER),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $list = $response->result;
        $this->assertInstanceOf(LspSignatureHelp::class, $list);
    }

    private function create(array $suggestions): LanguageServerTester
    {
        $builder = LanguageServerTesterBuilder::create();
        return $builder->addHandler(new SignatureHelpHandler(
            $builder->workspace(),
            $this->createHelper(),
            $this->createClientCapabilities(true)
        ))->build();
    }

    private function createClientCapabilities(
        bool $supportSignatureHelp = true
    ): ClientCapabilities {
        $capabilities = new ClientCapabilities();
        $capabilities->textDocument = new TextDocumentClientCapabilities();

        if (false === $supportSignatureHelp) {
            return $capabilities;
        }

        $signatureHelpCapabilities = new SignatureHelpClientCapabilities();
        $capabilities->textDocument->signatureHelp = $signatureHelpCapabilities;

        return $capabilities;
    }

    private function createHelper(): SignatureHelper
    {
        return new class() implements SignatureHelper {
            public function signatureHelp(TextDocument $textDocument, ByteOffset $offset): SignatureHelp
            {
                $help = new SignatureHelp([], 0);
                return $help;
            }
        };
    }
}
