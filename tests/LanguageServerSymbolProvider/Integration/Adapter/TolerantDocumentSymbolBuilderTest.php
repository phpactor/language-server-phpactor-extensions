<?php

namespace Phpactor\Extension\LanguageServer\Tests\ymbolProvider\Integration\Adapter;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerSymbolProvider\Adapter\TolerantDocumentSymbolBuilder;
use Phpactor\Extension\LanguageServerSymbolProvider\Model\DocumentSymbolBuilder;
use Phpactor\LanguageServerProtocol\DocumentSymbol;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\SymbolKind;

class TolerantDocumentSymbolBuilderTest extends TestCase
{
    /**
     * @dataProvider provideSource
     */
    public function testBuildDocumentSymbol(string $source, array $expected): void
    {
        self::assertEquals($expected, (new TolerantDocumentSymbolBuilder(new Parser()))->build($source));
    }

    public function provideSource(): Generator
    {
        yield 'nothing' => [
            '<?php',
            []
        ];

        yield 'class' => [
            '<?php class Foo {}',
            [
                new DocumentSymbol(
                    'Foo',
                    SymbolKind::CLASS_,
                    new Range(new Position(0, 6), new Position(0, 18)),
                    new Range(new Position(0, 12), new Position(0, 15)),
                ),
            ]
        ];
    }
}
