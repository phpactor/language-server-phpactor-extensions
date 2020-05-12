<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use LanguageServerProtocol\Location as LspLocation;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\Range;
use Phpactor\Extension\LanguageServerBridge\Tests\Converter\Exception\CouldNotLoadFileContents;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\TextDocumentUri;

class LocationConverter
{
    /**
     * @var Workspace
     */
    private $workspace;

    public function __construct(Workspace $workspace)
    {
        $this->workspace = $workspace;
    }


    public function toLspLocations(Locations $locations): array
    {
        $lspLocations = [];
        foreach ($locations as $location) {
            $lspLocations[] = $this->toLspLocation($location);
        }

        return $lspLocations;
    }

    public function toLspLocation(Location $location): LspLocation
    {
        $text = $this->loadText($location->uri());
        $position = $this->offsetToPosition($text, $location->offset()->toInt());

        return new LspLocation($location->uri()->__toString(), new Range($position, $position));
    }

    private function offsetToPosition(string $text, int $offset): Position
    {
        $text = substr($text, 0, $offset);
        $line = mb_substr_count($text, PHP_EOL);

        if ($line === 0) {
            return new Position($line, mb_strlen($text));
        }

        $lastNewLinePos = mb_strrpos($text, PHP_EOL);
        $remainingLine = mb_substr($text, $lastNewLinePos + mb_strlen(PHP_EOL));
        $char = mb_strlen($remainingLine);

        return new Position($line, $char);
    }

    private function loadText(TextDocumentUri $uri): string
    {
        if ($this->workspace->has($uri)) {
            return $this->workspace->get($uri)->text;
        }

        $contents = @file_get_contents($uri);

        if (false === $contents) {
            throw new CouldNotLoadFileContents(sprintf(
                'Could not load file contents "%s"',
                $uri
            ));
        }

        return $contents;
    }
}
