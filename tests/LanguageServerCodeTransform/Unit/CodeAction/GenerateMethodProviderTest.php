<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\CodeAction;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\RangeConverter;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\GenerateMethodProvider;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\GenerateMethodCommand;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\LanguageServerProtocol\CodeAction;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\TextDocument\ByteOffsetRange;
use function Amp\Promise\wait;

class GenerateMethodProviderTest extends TestCase
{
    /**
     * @dataProvider provideDiagnosticsTestData
     */
    public function testDiagnostics(string $text): void
    {
        $result = OffsetExtractor::create()->registerRange('diagnosticsRanges', '{{', '}}')->parse($text);
        $expectedDiagnostics = array_map(function (ByteOffsetRange $byteRange) use ($result) {
            return new Diagnostic(
                RangeConverter::toLspRange($byteRange, $result->source()),
                'Generate method',
                DiagnosticSeverity::INFORMATION,
                null,
                'phpactor'
            );
        }, $result->ranges('diagnosticsRanges'));

        $provider = $this->createProvider();

        self::assertEquals(
            $expectedDiagnostics,
            wait($provider->provideDiagnostics(new TextDocumentItem('file:///somefile.php', 'php', 1, $result->source())))
        );
    }

    public function provideDiagnosticsTestData(): Generator
    {
        yield 'Empty file' => [
                '<?php '
            ];
        yield 'Class with no methods calls' => [
                '<?php 
				class Class1 
				{
					public function __construct() {
					}

					public function someOtherMethod() {
						$var = 5;
					}
				}
				'
            ];
        yield 'Class with methods calls' => [
                '<?php 
				class Class1 
				{
					public function __construct() {
					}

					public function someOtherMethod() {
						$var = 5;
						$this->{{methodThatIsCalled}}()->{{otherMethod}}();
						$this->{$var}();
                        self::{{someStaticMethod}}();
					}
				}
				'
            ];
    }

    public function testNoActionsAreProvidedWhenRangeIsNotAnInsertionPoint(): void
    {
        $provider = $this->createProvider();
        self::assertEquals(
            [],
            wait($provider->provideActionsFor(new TextDocumentItem('file:///somefile.php', 'php', 1, '<?php $var = 5;'), new Range(
                new Position(1, 7),
                new Position(1, 9),
            )))
        );
    }
    /** @dataProvider provideActionsTestData */
    public function testProvideActions(string $text, bool $shouldSucceed): void
    {
        $result = OffsetExtractor::create()
            ->registerRange('diagnosticsRange', '{{', '}}')
            ->registerOffset('selection', '<>')
            ->parse($text);

        $provider = $this->createProvider();
        $selectionPosition = PositionConverter::byteOffsetToPosition($result->offset('selection'), $result->source());
        $uri = 'file:///somefile.php';

        if ($shouldSucceed) {
            $expectedDiagnostic = new Diagnostic(
                RangeConverter::toLspRange($result->range('diagnosticsRange'), $result->source()),
                'Generate method',
                DiagnosticSeverity::INFORMATION,
                null,
                'phpactor'
            );
            $codeActions = [
                CodeAction::fromArray([
                    'title' =>  'Generate method (if not exists)',
                    'kind' => GenerateMethodProvider::KIND,
                    'diagnostics' => [ $expectedDiagnostic ],
                    'command' => new Command(
                        'Generate method',
                        GenerateMethodCommand::NAME,
                        [
                            $uri,
                            $result->offset('selection')->toInt()
                        ]
                    )
                ])
            ];
        } else {
            $codeActions = [];
        }

        self::assertEquals(
            $codeActions,
            wait($provider->provideActionsFor(new TextDocumentItem($uri, 'php', 1, $result->source()), new Range(
                $selectionPosition,
                $selectionPosition,
            )))
        );
    }

    public function provideActionsTestData(): Generator
    {
        yield
            'Empty file' => [
                '<?php <>',
                false
            ];

        yield 'Outside methods calls' => [
            '<?php 
class Class1 
{
    public function __construct() {
    }

    public function someOtherMethod() {
        $v<>ar = 5;
        $this->methodThatIsCalled();
    }
    }
',
                            false
        ];
        yield 'Instance method call' => [
                '<?php 
class Class1 
{
    public function __construct() {
    }

    public function someOtherMethod() {
        $var = 5;
        $this->{{methodTh<>atIsCalled}}();
    }
    }
',
                            true
            ];
        yield 'Static method call' => [
                '<?php 
class Class1 
{
    public function __construct() {
    }

    public function someOtherMethod() {
        $var = 5;
        self::{{methodTh<>atIsCalled}}();
    }
    }
',
                            true
            ];
        yield 'Dynamic method name call' => [
                '<?php 
class Class1 
{
    public function __construct() {
    }

    public function someOtherMethod() {
        $var = 5;
        self::{$v<>ar}();
    }
    }
',
                            false
            ];
    }
    private function createProvider(): GenerateMethodProvider
    {
        return new GenerateMethodProvider(new Parser());
    }
}
