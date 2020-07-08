<?php

namespace Phpactor\Extension\LanguageServerCompletion\Tests\Unit;

use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\SignatureHelp;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\Extension\LanguageServerCompletion\Tests\IntegrationTestCase;
use Phpactor\LanguageServer\Core\Rpc\ResponseMessage;

class LanguageServerCompletionExtensionTest extends IntegrationTestCase
{
    public function testComplete()
    {
        $tester = $this->createTester();
        $tester->initialize();

        $document = new TextDocumentItem('/test', 'php', 1, 'hello');
        $position = new Position(1, 1);
        $tester->openDocument($document);

        $response = $tester->dispatchAndWait(1, 'textDocument/completion', [
            'textDocument' => new TextDocumentIdentifier('/test'),
            'position' => $position,
        ]);

        $this->assertInstanceOf(ResponseMessage::class, $response);
        $this->assertNull($response->error);
        $this->assertInstanceOf(CompletionList::class, $response->result);
    }

    public function testSignatureProvider()
    {
        $tester = $this->createTester();
        $tester->initialize();

        $document = new TextDocumentItem('/test', 'php', 1, 'hello');
        $position = new Position(1, 1);
        $tester->openDocument($document);
        $identifier = new TextDocumentIdentifier($document->uri);

        $response = $tester->dispatchAndWait(1, 'textDocument/signatureHelp', [
            'textDocument' => new TextDocumentIdentifier('/test'),
            'position' => $position,
        ]);

        $this->assertInstanceOf(ResponseMessage::class, $response);
        $this->assertNull($response->error);
        $this->assertNull($response->result);
    }
}
