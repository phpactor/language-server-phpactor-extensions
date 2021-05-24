<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Model\NameImport;

use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentUri;

class NameImporter implements Command
{
    /**
     * @var ImportName
     */
    private $importName;


    public function __construct(
        ImportName $importName
    ) {
        $this->importName = $importName;
    }

    public function __invoke(
        TextDocumentItem $document,
        int $offset,
        string $type,
        string $fqn,
        ?string $alias = null
    ): NameImporterResult {
        $sourceCode = SourceCode::fromStringAndPath(
            $document->text,
            TextDocumentUri::fromString($document->uri)->path()
        );

        $nameImport = $type === 'function' ?
            NameImport::forFunction($fqn, $alias) :
            NameImport::forClass($fqn, $alias);

        try {
            $textEdits = $this->importName->importName(
                $sourceCode,
                ByteOffset::fromInt($offset),
                $nameImport
            );
            $lspTextEdits = TextEditConverter::toLspTextEdits($textEdits, $document->text);
            return NameImporterResult::createResult($nameImport, $lspTextEdits);
        } catch (NameAlreadyImportedException $error) {
            if ($error->existingName() === $fqn) {
                return NameImporterResult::createEmptyResult();
            }

            $name = FullyQualifiedName::fromString($fqn);
            $prefix = 'Aliased';
            if (isset($name->toArray()[0])) {
                $prefix = $name->toArray()[0];
            }

            return $this->__invoke($document, $offset, $type, $fqn, $prefix . $error->name());
        } catch (AliasAlreadyUsedException $error) {
            $prefix = 'Aliased';
            return $this->__invoke($document, $offset, $type, $fqn, $prefix . $error->name());
        } catch (TransformException $error) {
            return NameImporterResult::createErrorResult($error);
        }
    }
}
