<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use LanguageServerProtocol\WorkspaceEdit;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\TextDocument\TextDocumentUri;

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

    /**
     * @var TextEditConverter
     */
    private $textEditConverter;

    /**
     * @var ClientApi
     */
    private $client;

    public function __construct(
        ImportClass $importClass,
        Workspace $workspace,
        TextEditConverter $textEditConverter,
        ClientApi $client
    )
    {
        $this->importClass = $importClass;
        $this->workspace = $workspace;
        $this->textEditConverter = $textEditConverter;
        $this->client = $client;
    }

    public function __invoke(string $uri, int $offset, string $fqn): Promise
    {
        $document = $this->workspace->get($uri);
        $textEdits = $this->importClass->importClass(
            SourceCode::fromStringAndPath(
                $document->text,
                TextDocumentUri::fromString($document->uri)->path()
            ),
            $offset,
            $fqn
        );

        return $this->client->workspace()->applyEdit(new WorkspaceEdit(
            $this->textEditConverter->toLspTextEdits($textEdits, $document->text)
        ), 'Import class');
    }
}
