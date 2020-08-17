<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\CodeAction;

use Phpactor\Extension\LanguageServerCodeTransform\Tests\IntegrationTestCase;
use Phpactor\LanguageServerProtocol\CodeActionContext;
use Phpactor\LanguageServerProtocol\CodeActionParams;
use Phpactor\LanguageServerProtocol\CodeActionRequest;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;

class ImportClassProviderTest extends IntegrationTestCase
{
    public function testImportProvider(): void
    {
        $this->workspace()->reset();
        $tester = $this->container()->get(LanguageServerBuilder::class)->tester(
            ProtocolFactory::initializeParams($this->workspace()->path())
        );
        assert($tester instanceof LanguageServerTester);
        $tester->textDocument()->open('file:///foobar', '<?php new MissingName();');

        $result = $tester->requestAndWait(CodeActionRequest::METHOD, new CodeActionParams(
            ProtocolFactory::textDocumentIdentifier('file:///foobar'),
            ProtocolFactory::range(0, 0, 0, 15),
            new CodeActionContext([])
        ));

        $tester->assertSuccess($result);

        self::assertCount(1, $result->result);
    }
}
