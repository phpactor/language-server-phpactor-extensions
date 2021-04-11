<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

final class LocatedTextEditsMap
{
    /**
     * @var array<string,TextEdits>
     */
    private $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public static function fromLocatedEdits(array $locatedEdits)
    {
        $map = new self([]);
        foreach ($locatedEdits as $locationEdit) {
            $map = $map->withTextEdit($locationEdit);
        }

        return $map;
    }

    public function withTextEdit(LocatedTextEdit $edit): self
    {
        $map = $this->map;
        $uri = $edit->documentUri();
        $edit = $edit->textEdit();

        if (!isset($map[$uri->__toString()])) {
            $map[$uri->__toString()] = new TextEdits();
        }

        $map[$uri->__toString()] = $map[$uri->__toString()]->add($edit);

        return new self($map);
    }

    public function toLocatedTextEdits(): array
    {
        $locatedTextEdits = [];
        foreach ($this->map as $uri => $edits) {
            $locatedTextEdits[] = new LocatedTextEdits($edits, TextDocumentUri::fromString($uri));
        }

        return $locatedTextEdits;
    }
}
