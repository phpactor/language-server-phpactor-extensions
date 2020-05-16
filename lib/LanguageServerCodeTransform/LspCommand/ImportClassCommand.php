<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\LanguageServer\Core\Session\Workspace;

class ImportClassCommand
{
    /**
     * @var ImportClass
     */
    private $importClass;

    /**
     * @var Workspace
     */
    private $workspace;

    public function __construct(ImportClass $importClass, Workspace $workspace)
    {
        $this->importClass = $importClass;
        $this->workspace = $workspace;
    }

    public function __invoke(string $uri, int $offset, string $fqn): Promise
    {
        $document = $this->workspace->get($uri);
        $textEdits = $this->importClass->importClass(SourceCode::fromStringAndPath($document->text, $document->uri), $offset, $fqn);
    }
}
