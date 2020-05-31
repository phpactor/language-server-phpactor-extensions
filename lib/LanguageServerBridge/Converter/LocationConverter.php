<?php

namespace Phpactor\Extension\LanguageServerBridge\Converter;

use LanguageServerProtocol\Location as LspLocation;
use LanguageServerProtocol\Range;
use Phpactor\Extension\LanguageServerBridge\Converter\Exception\CouldNotLoadFileContents;
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

    /**
     * @var OffsetConverter
     */
    private $offsetConverter;

    public function __construct(Workspace $workspace, ?OffsetConverter $offsetConverter = null)
    {
        $this->workspace = $workspace;
        $this->offsetConverter = $offsetConverter ?: new OffsetConverter();
    }

    public function toLspLocations(Locations $locations): array
    {
        $lspLocations = [];
        foreach ($locations as $location) {
            try {
                $lspLocations[] = $this->toLspLocation($location);
            } catch (CouldNotLoadFileContents $couldNotLoad) {
                // ignore stale records
                continue;
            }
        }

        return $lspLocations;
    }

    public function toLspLocation(Location $location): LspLocation
    {
        $text = $this->loadText($location->uri());
        $position = $this->offsetConverter->offsetToPosition($text, $location->offset());

        return new LspLocation($location->uri()->__toString(), new Range($position, $position));
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
