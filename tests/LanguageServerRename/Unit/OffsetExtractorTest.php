<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;

class OffsetExtractorTest extends TestCase
{
    public function testPoint(): void
    {
        list(
            'selection' => $selection,
            'newSource' => $newSource
        ) = OffsetExtractor::create()
            ->registerPoint('selection', '<>')
            ->parse('Test string with<> selector');
        $this->assertIsArray($selection);
        $this->assertEquals(1, count($selection));
        $this->assertEquals(ByteOffset::fromInt(16), $selection[0]);
        $this->assertEquals('Test string with selector', $newSource);
    }

    public function testPoints(): void
    {
        list(
            'selection' => $selection,
            'newSource' => $newSource
        ) = OffsetExtractor::create()
            ->registerPoint('selection', '<>')
            ->parse('Test string with<> two select<>ors');
        $this->assertEquals([
            ByteOffset::fromInt(16),
            ByteOffset::fromInt(27)
        ], $selection);
        $this->assertEquals('Test string with two selectors', $newSource);
    }
    
    public function testRange(): void
    {
        list(
            'textEdit' => $textEdit,
            'newSource' => $newSource
        ) = OffsetExtractor::create()
            ->registerRange('textEdit', '{{', '}}')
            ->parse('Test string {{with}} selector');
        $this->assertEquals([ByteOffsetRange::fromInts(12, 16)], $textEdit);
        $this->assertEquals('Test string with selector', $newSource);
    }

    public function testRanges(): void
    {
        list(
            'textEdit' => $textEdit,
            'newSource' => $newSource
        ) = OffsetExtractor::create()
            ->registerRange('textEdit', '{{', '}}')
            ->parse('Test string {{with}} two {{selectors}}');
        $this->assertEquals(
            [
                ByteOffsetRange::fromInts(12, 16),
                ByteOffsetRange::fromInts(21, 30),
            ],
            $textEdit
        );
        $this->assertEquals('Test string with two selectors', $newSource);
    }
}
