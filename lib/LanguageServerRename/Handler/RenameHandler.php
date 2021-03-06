<?php

namespace Phpactor\Extension\LanguageServerRename\Handler;

use Amp\Promise;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\RangeConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\LanguageServerProtocol\PrepareRenameParams;
use Phpactor\LanguageServerProtocol\PrepareRenameRequest;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\RenameOptions;
use Phpactor\LanguageServerProtocol\RenameParams;
use Phpactor\LanguageServerProtocol\RenameRequest;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;

class RenameHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Renamer
     */
    private $renamer;
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var TextDocumentLocator
     */
    private $documentLocator;

    public function __construct(Workspace $workspace, TextDocumentLocator $documentLocator, Renamer $renamer)
    {
        $this->renamer = $renamer;
        $this->workspace = $workspace;
        $this->documentLocator = $documentLocator;
    }
    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            PrepareRenameRequest::METHOD => 'prepareRename',
            RenameRequest::METHOD => 'rename',
        ];
    }
    /**
     * @return Promise<WorkspaceEdit>
     */
    public function rename(RenameParams $params): Promise
    {
        return \Amp\call(function () use ($params) {
            return $this->resultToWorkspaceEdit(
                $this->renamer->rename(
                    $document = $this->documentLocator->get(TextDocumentUri::fromString($params->textDocument->uri)),
                    PositionConverter::positionToByteOffset(
                        $params->position,
                        (string)$document
                    ),
                    $params->newName
                )
            );
        });
    }
    /**
     * @return Promise<Range>
     */
    public function prepareRename(PrepareRenameParams $params): Promise
    {
        // https://microsoft.github.io/language-server-protocol/specification#textDocument_prepareRename
        return \Amp\call(function () use ($params) {
            $range = $this->renamer->getRenameRange(
                $document = $this->documentLocator->get(TextDocumentUri::fromString($params->textDocument->uri)),
                PositionConverter::positionToByteOffset(
                    $params->position,
                    (string)$document
                ),
            );
            if ($range == null) {
                return null;
            }
            return RangeConverter::toLspRange($range, (string)$document);
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->renameProvider = new RenameOptions(true);
    }
    
    private function resultToWorkspaceEdit(iterable $results): WorkspaceEdit
    {
        $edits = [];
        $documentChanges = [];
        foreach ($results as $result) {
            /** @var RenameResult $result */
            $version = $this->getDocumentVersion((string)$result->documentUri());
            $edits[] = new TextDocumentEdit(
                new VersionedTextDocumentIdentifier(
                    (string)$result->documentUri(),
                    $version
                ),
                TextEditConverter::toLspTextEdits(
                    $result->textEdits(),
                    (string)$this->documentLocator->get($result->documentUri())
                )
            );
        }
        return new WorkspaceEdit(null, $edits);
    }

    private function getDocumentVersion(string $uri): int
    {
        return $this->workspace->has($uri) ? $this->workspace->get($uri)->version : 0;
    }
}
