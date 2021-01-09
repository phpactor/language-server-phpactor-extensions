<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerRename\Tests\OffsetExtractor;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\TextDocument\ByteOffset;

class OffsetExtractorTest extends TestCase
{
    public function testPoint(): void
    {
        $extractor = new OffsetExtractor();
        $extractor->registerPoint("selection", "<>");
        list(
            "selection" => $selection,
            "newSource" => $newSource
        ) = $extractor->parse("Test string with<> selector");
        $this->assertEquals(16, $selection);
        $this->assertEquals("Test string with selector", $newSource);
    }
    
    public function testPointWithCreator(): void
    {
        $extractor = new OffsetExtractor();
        $extractor->registerPoint("selection", "<>", function (int $offset, string $source) {
            return ByteOffset::fromInt($offset);
        });
        list(
            "selection" => $selection,
            "newSource" => $newSource
        ) = $extractor->parse("Test string with<> selector");
        $this->assertInstanceOf(ByteOffset::class, $selection);
        $this->assertEquals(16, $selection->toInt());
        $this->assertEquals("Test string with selector", $newSource);
    }
    
    public function testRange(): void
    {
        $extractor = new OffsetExtractor();
        $extractor->registerRange("textEdit", "{{", "}}");
        list(
            "textEdit" => $textEdit,
            "newSource" => $newSource
        ) = $extractor->parse("Test string {{with}} selector");
        $this->assertIsArray($textEdit);
        $this->assertEquals(['start' => 12, 'end' => 16], $textEdit);
        $this->assertEquals("Test string with selector", $newSource);
	}
	
	public function testRangeWithCreator(): void
    {
        $extractor = new OffsetExtractor();
        $extractor->registerRange("textEdit", "{{", "}}", function(int $start, int $end, string $source){
			return new Range(
				PositionConverter::byteOffsetToPosition(ByteOffset::fromInt($start), $source),
				PositionConverter::byteOffsetToPosition(ByteOffset::fromInt($end), $source),
			);
		});
        list(
            "textEdit" => $textEdit,
            "newSource" => $newSource
        ) = $extractor->parse("Test string {{with}} selector");
        $this->assertInstanceOf(Range::class, $textEdit);
        $this->assertEquals(new Range(new Position(0, 12), new Position(0, 16)), $textEdit);
        $this->assertEquals("Test string with selector", $newSource);
    }
}
