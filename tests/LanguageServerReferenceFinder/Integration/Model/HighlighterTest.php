<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Tests\Integration\Model;

use Closure;
use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerReferenceFinder\Model\Highlighter;
use Phpactor\Extension\LanguageServerReferenceFinder\Model\Highlights;
use Phpactor\LanguageServerProtocol\DocumentHighlightKind;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TextDocument\ByteOffset;

class HighlighterTest extends TestCase
{
    /**
     * @dataProvider provideVariables
     */
    public function testHighlight(string $source, Closure $assertion)
    {
        [$source, $offset] = ExtractOffset::fromSource($source);
        $assertion(
            (new Highlighter(new Parser()))->highlightsFor(
                $source,
                ByteOffset::fromInt((int)$offset)
            )
        );
    }

    /**
     * @return Generator<mixed>
     */
    public function provideVariables(): Generator
    {
        yield 'none' => [
            '<?php',
            function (Highlights $highlights) {
                self::assertCount(0, $highlights);
            }
        ];

        yield 'one' => [
            '<?php $v<>ar;',
            function (Highlights $highlights) {
                self::assertCount(1, $highlights);
                self::assertEquals(DocumentHighlightKind::READ, $highlights->first()->kind);
            }
        ];

        yield 'two vars including method var' => [
            '<?php function foobar ($var) { $v<>ar; }',
            function (Highlights $highlights) {
                self::assertCount(2, $highlights);
            }
        ];
    }
}
