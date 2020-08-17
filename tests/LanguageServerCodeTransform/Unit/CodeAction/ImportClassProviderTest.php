<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\CodeAction;

use PHPUnit\Framework\TestCase;
use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\ImportClassProvider;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImportCandidateProvider;
use Phpactor\Extension\LanguageServerCodeTransform\Tests\IntegrationTestCase;
use Phpactor\Indexer\Adapter\Php\InMemory\InMemoryIndex;
use Phpactor\Indexer\Adapter\Php\InMemory\InMemorySearchIndex;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\LanguageServerProtocol\CodeActionContext;
use Phpactor\LanguageServerProtocol\CodeActionParams;
use Phpactor\LanguageServerProtocol\CodeActionRequest;
use Phpactor\LanguageServer\Handler\TextDocument\CodeActionHandler;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
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
