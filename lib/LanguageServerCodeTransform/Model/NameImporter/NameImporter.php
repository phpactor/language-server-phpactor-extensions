<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter;

use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport as ImportClassNameImport;
use Phpactor\CodeTransform\Domain\Refactor\ImportName as RefactorImportName;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentUri;

class NameImporter
{
    /**
     * @var RefactorImportName
     */
    private $importName;

    /**
     * @var Workspace
     */
    private $workspace;

    public function __construct(RefactorImportName $importName, Workspace $workspace)
    {
        $this->importName = $importName;
        $this->workspace = $workspace;
    }

    public function import(
        string $uri,
        int $offset,
        string $type,
        string $fqn,
        ?string $alias = null
    ): NameImporterResult {
        $document = $this->workspace->get($uri);
        $sourceCode = SourceCode::fromStringAndPath(
            $document->text,
            TextDocumentUri::fromString($document->uri)->path()
        );

        $nameImport = $type === 'function' ?
            ImportClassNameImport::forFunction($fqn, $alias) :
            ImportClassNameImport::forClass($fqn, $alias);

        try {
            $textEdits = $this->importName->importNameOnly(
                $sourceCode,
                ByteOffset::fromInt($offset),
                $nameImport
            );
        } catch (NameAlreadyImportedException $error) {
            if ($error->existingName() === $fqn) {
                return NameImporterResult::createEmptyResult();
            }

            $name = FullyQualifiedName::fromString($fqn);
            $prefix = 'Aliased';
            if (isset($name->toArray()[0])) {
                $prefix = $name->toArray()[0];
            }

            return $this->import($uri, $offset, $type, $fqn, $prefix . $error->name());
        } catch (AliasAlreadyUsedException $error) {
            $prefix = 'Aliased';
            return $this->import($uri, $offset, $type, $fqn, $prefix . $error->name());
        } catch (TransformException $error) {
            return NameImporterResult::createErrorResult($error);
        }

        $lspTextEdits = TextEditConverter::toLspTextEdits($textEdits, $document->text);
        return NameImporterResult::createResult($nameImport, $lspTextEdits);
    }
}
