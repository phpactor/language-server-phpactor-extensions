<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\CodeAction;

use Amp\Success;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerBridge\Converter\RangeConverter;
use Phpactor\Extension\LanguageServerCodeTransform\CodeAction\GenerateMethodProvider;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\TextDocument\ByteOffsetRange;

class GenerateMethodProviderTest extends TestCase
{
    /** @dataProvider provideDiagnosticsTestData */
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

        $provider = new GenerateMethodProvider(new Parser());
        $this->assertEquals(
            new Success($expectedDiagnostics),
            $provider->provideDiagnostics(new TextDocumentItem('file:///somefile.php', 'php', 1, $result->source()))
        );
    }

    public function provideDiagnosticsTestData(): array
    {
        return [
            'Empty file' => [
                '<?php '
            ],
            'Class with no methods calls' => [
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
            ],
            'Class with methods calls' => [
                '<?php 
				class Class1 
				{
					public function __construct() {
					}

					public function someOtherMethod() {
						$var = 5;
						$this->{{methodThatIsCalled}}()->{{otherMethod}}();
						$this->{$var}();
					}
				}
				'
            ],
        ];
    }
}
