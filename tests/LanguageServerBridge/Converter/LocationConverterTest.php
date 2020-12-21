<?php

namespace Phpactor\Extension\LanguageServer\Tests\ridge\Converter;

use Generator;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerBridge\Tests\IntegrationTestCase;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\LanguageServerProtocol\Location as LspLocation;

class LocationConverterTest extends IntegrationTestCase
{
    protected function setUp(): void: void
    {
        $this->workspace()->reset();
    }

    public function testConvertsPhpactorLocationsToLspLocations(): void
    {
        $this->workspace()->put('test.php', '012345678');

        $locations = new Locations([
            Location::fromPathAndOffset(
                $this->workspace()->path('test.php'),
                2
            )
        ]);

        $expected = [
            new LspLocation('file://' . $this->workspace()->path('test.php'), new Range(
                new Position(0, 2),
                new Position(0, 2),
            ))
        ];

        $workspace = new Workspace();

        $converter = new LocationConverter($workspace);

        self::assertEquals($expected, $converter->toLspLocations($locations));
    }

    public function testIgnoresNonExistingFiles(): void
    {
        $this->workspace()->put('test.php', '12345678');

        $locations = new Locations([
            Location::fromPathAndOffset($this->workspace()->path('test.php'), 2),
            Location::fromPathAndOffset($this->workspace()->path('test-no.php'), 2)
        ]);

        $expected = [
            new LspLocation('file://' . $this->workspace()->path('test.php'), new Range(
                new Position(0, 2),
                new Position(0, 2),
            ))
        ];

        $workspace = new Workspace();
        $converter = new LocationConverter($workspace);
        self::assertEquals($expected, $converter->toLspLocations($locations));
    }

    /**
     * @dataProvider provideDiskLocations
     * @dataProvider provideMultibyte
     * @dataProvider provideWorkspaceLocations
     * @dataProvider provideOutOfRange
     */
    public function testLocationToLspLocation(string $text, ?string $workspaceText, int $offset, Range $expectedRange): void
    {
        $this->workspace()->put('test.php', $text);

        $location = Location::fromPathAndOffset($this->workspace()->path('test.php'), $offset);

        $uri = 'file://' . $this->workspace()->path('test.php');

        $workspace = new Workspace();

        if ($workspaceText !== null) {
            $textDocument = new TextDocumentItem($uri, 'php', 1, $workspaceText);
            $workspace->open($textDocument);
        }

        $expected = new LspLocation($uri, $expectedRange);

        self::assertEquals($expected, (new LocationConverter($workspace))->toLspLocation($location));
        ;
    }

    /**
     * @return Generator<mixed>
     */
    public function provideOutOfRange(): Generator
    {
        yield 'out of upper range' => [
            '12345',
            null,
            10,
            $this->createRange(0, 4, 0, 4)
        ];
    }

    /**
     * @return Generator<mixed>
     */
    public function provideMultibyte(): Generator
    {
        yield '4 byte char 1st char' => [
            '😼😼😼😼😼',
            null,
            4,
            $this->createRange(0, 4, 0, 4)
        ];

        yield '4 byte char 2nd char' => [
            '😼😼😼😼😼',
            null,
            5,
            $this->createRange(0, 8, 0, 8)
        ];

        yield '4 byte char 4th char' => [
            '😼😼😼😼😼',
            null,
            16,
            $this->createRange(0, 16, 0, 16)
        ];
    }

    /**
     * @return Generator<mixed>
     */
    public function provideDiskLocations(): Generator
    {
        yield 'single line' => [
            '12345678',
            null,
            2,
            $this->createRange(0, 2, 0, 2)
        ];

        yield 'second line' => [
            "12\n345\n678",
            null,
            4,
            $this->createRange(1, 1, 1, 1)
        ];

        yield 'third line first char' => [
            "12\n345\n678",
            null,
            8,
            $this->createRange(2, 1, 2, 1)
        ];
    }

    /**
     * @return Generator<mixed>
     */
    public function provideWorkspaceLocations(): Generator
    {
        yield 'workspace same as disk' => [
            '12345678',
            '12345678',
            7,
            $this->createRange(0, 7, 0, 7)
        ];

        yield 'workspace different' => [
            "12\n345\n678",
            "12345678",
            7,
            $this->createRange(0, 7, 0, 7)
        ];
    }

    private function createRange(int $line1, int $offset1, int $line2, int $offset2): Range
    {
        return new Range(new Position($line1, $offset1), new Position($line2, $offset2));
    }
}
