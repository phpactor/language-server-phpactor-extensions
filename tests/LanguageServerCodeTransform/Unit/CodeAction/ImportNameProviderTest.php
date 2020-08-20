<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\CodeAction;

use Generator;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerCodeTransform\Tests\IntegrationTestCase;
use Phpactor\LanguageServerProtocol\CodeActionContext;
use Phpactor\LanguageServerProtocol\CodeActionParams;
use Phpactor\LanguageServerProtocol\CodeActionRequest;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServer\LanguageServerBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\TestUtils\ExtractOffset;
use function Amp\Promise\wait;
use function Amp\delay;

class ImportNameProviderTest extends IntegrationTestCase
{
    /**
     * @dataProvider provideImportProvider
     */
    public function testImportProvider(string $manifest, int $expectedCount, int $expectedDiagnosticCount): void
    {
        $this->workspace()->reset();
        $this->workspace()->loadManifest($manifest);
        $tester = $this->container()->get(LanguageServerBuilder::class)->tester(
            ProtocolFactory::initializeParams($this->workspace()->path())
        );
        $tester->initialize();
        assert($tester instanceof LanguageServerTester);
        $subject = $this->workspace()->getContents('subject.php');
        [ $source, $offset ] = ExtractOffset::fromSource($subject);
        $tester->textDocument()->open('file:///foobar', $source);
        $result = $tester->requestAndWait(CodeActionRequest::METHOD, new CodeActionParams(
            ProtocolFactory::textDocumentIdentifier('file:///foobar'),
            new Range(
                ProtocolFactory::position(0, 0),
                PositionConverter::intByteOffsetToPosition((int)$offset, $source)
            ),
            new CodeActionContext([])
        ));

        $tester->assertSuccess($result);

        self::assertCount($expectedCount, $result->result, 'Number of code actions');

        $tester->textDocument()->update('file:///foobar', $source);
        wait(delay(100));
        $diagnostics = $tester->transmitter()->filterByMethod('textDocument/publishDiagnostics')->shiftNotification();
        self::assertNotNull($diagnostics);
        $diagnostics = $diagnostics->params['diagnostics'];
        self::assertEquals($expectedDiagnosticCount, count($diagnostics), 'Number of diagnostics');
    }

    /**
     * @return Generator<mixed>
     */
    public function provideImportProvider(): Generator
    {
        yield 'one code action for missing name' => [
            <<<'EOT'
// File: subject.php
<?php new MissingName();'
// File: Foobar/MissingName.php
<?php namespace Foobar; class MissingName {}
EOT
        , 1, 1
        ];

        yield 'zero code actions and one diagnostic for non existant class' => [
            <<<'EOT'
// File: subject.php
<?php new MissingNameFoo();'
EOT
        , 0, 1
        ];

        yield 'zero code actions and one diagnostic for namespaced non-existant class' => [
            <<<'EOT'
// File: subject.php
<?php namespace Bar; new MissingNameFoo();'
EOT
        , 0, 1
        ];

        yield 'one code action for missing global class name' => [
            <<<'EOT'
// File: subject.php
<?php namespace Foobar; function foobar(): Generator { yield 12; }'
// File: Generator.php
<?php class Generator {}
EOT
        , 1, 1
        ];

        yield 'no code action or diagnostics for missing global function name' => [
            <<<'EOT'
// File: subject.php
<?php namespace Foobar; sprintf('foo %s', 'bar')
// File: sprintf.php
<?php function sprintf($pattern, ...$args) {}
EOT
        , 0, 0
        ];

        yield 'no diagnostics for class declared in same namespace' => [
            <<<'EOT'
// File: subject.php
<?php

namespace Phpactor\Extension;

class Test
{
    public function testBar()
    {
        new Bar();
    }
}

class Bar
{
}
EOT
        , 0, 0
        ];
    }
}
