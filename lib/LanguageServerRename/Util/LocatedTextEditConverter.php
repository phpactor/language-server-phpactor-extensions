<?php

namespace Phpactor\Extension\LanguageServerRename\Util;

use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;

class LocatedTextEditConverter
{
    public static function toWorkspaceEdit(LocatedTextEditsMap $map): WorkspaceEdit
    {
        $documentEdits = [];
        foreach ($map->toLocatedTextEdits() as $result) {
            $version = $this->getDocumentVersion((string)$result->documentUri());
            $documentEdits[] = new TextDocumentEdit(
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
        return new WorkspaceEdit(null, $documentEdits);
    }
}
