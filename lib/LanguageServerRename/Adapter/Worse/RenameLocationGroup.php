<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse;

use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentUri;

class RenameLocationGroup
{
    /**
     * @var TextDocumentUri
     */
    private $uri;
    /**
     * @var array
     */
    private $locations;

    /** @param Location[] $locations */
    public function __construct(TextDocumentUri $uri, array $locations)
    {
        $this->uri = $uri;
        $this->locations = $locations;
    }

    public function uri(): TextDocumentUri
    {
        return $this->uri;
    }
    /** @return Location[] */
    public function locations(): array
    {
        return $this->locations;
    }
}
