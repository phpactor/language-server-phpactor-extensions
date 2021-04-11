<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Util;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Tests\Util\OffsetExtractor;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use RuntimeException;

class OffsetExtractorTest extends TestCase
{
    public function testPoint(): void
    {
        $extractor = OffsetExtractor::create()
            ->registerPoint('selection', '<>')
            ->parse('Test string with<> selector');

        $selection = $extractor->point('selection');
        $newSource = $extractor->source();
        
        $this->assertEquals(ByteOffset::fromInt(16), $selection);
        $this->assertEquals('Test string with selector', $newSource);
    }

    public function testExceptionWhenNoPointIsFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No "selection" points found');

        $extractor = OffsetExtractor::create()
            ->registerPoint('selection', '<>')
            ->parse('Test string without selector');

        $extractor->point('selection');
    }

    public function testExceptionWhenNoPointIsRegistered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No point registered');

        $extractor = OffsetExtractor::create()
            ->parse('Test string without selector');

        $extractor->point('selection');
    }

    public function testPoints(): void
    {
        $extractor = OffsetExtractor::create()
            ->registerPoint('selection', '<>')
            ->parse('Test string with<> two select<>ors');
        $selection = $extractor->points('selection');
        $newSource = $extractor->source();
        $this->assertEquals([
            ByteOffset::fromInt(16),
            ByteOffset::fromInt(27)
        ], $selection);
        $this->assertEquals('Test string with two selectors', $newSource);
    }
    
    public function testRange(): void
    {
        $extractor = OffsetExtractor::create()
            ->registerRange('textEdit', '{{', '}}')
            ->parse('Test string {{with}} selector');

        $textEdit = $extractor->ranges('textEdit');
        $newSource = $extractor->source();

        $this->assertEquals([ByteOffsetRange::fromInts(12, 16)], $textEdit);
        $this->assertEquals('Test string with selector', $newSource);
    }

    public function testRanges(): void
    {
        $extractor = OffsetExtractor::create()
            ->registerRange('textEdit', '{{', '}}')
            ->parse('Test string {{with}} two {{selectors}}');
        $textEdit = $extractor->ranges('textEdit');
        $newSource = $extractor->source();
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
