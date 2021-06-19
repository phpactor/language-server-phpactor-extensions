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
        bool $updateReferences,
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
            $byteOffset = ByteOffset::fromInt($offset);
            if ($updateReferences) {
                $textEdits = $this->importName->importName($sourceCode, $byteOffset, $nameImport);
            } else {
                $textEdits = $this->importName->importNameOnly($sourceCode, $byteOffset, $nameImport);
            }
            $lspTextEdits = TextEditConverter::toLspTextEdits($textEdits, $document->text);
            return NameImporterResult::createResult($nameImport, $lspTextEdits);
        } catch (NameAlreadyImportedException $error) {
            if ($error->existingFQN() === $fqn) {
                return $this->createResultForAlreadyImportedFQN($nameImport, $error);
            }

            $name = FullyQualifiedName::fromString($fqn);
            $prefix = 'Aliased';
            if (isset($name->toArray()[0])) {
                $prefix = $name->toArray()[0];
            }

            return $this->__invoke($document, $offset, $type, $fqn, $updateReferences, $prefix . $error->name());
        } catch (AliasAlreadyUsedException $error) {
            $prefix = 'Aliased';
            return $this->__invoke($document, $offset, $type, $fqn, $updateReferences, $prefix . $error->name());
        } catch (TransformException $error) {
            return NameImporterResult::createErrorResult($error);
        }
    }

    private function createResultForAlreadyImportedFQN(
        NameImport $nameImport,
        NameAlreadyImportedException $error
    ): NameImporterResult {
        if ($error->existingName() !== $nameImport->name()->head()->__toString()) {
            $alias = $error->existingName();
        } else {
            $alias = null;
        }

        $nameImport = $nameImport->type() === 'function' ?
            NameImport::forFunction($error->existingFQN(), $alias) :
            NameImport::forClass($error->existingFQN(), $alias);

        return NameImporterResult::createResult($nameImport, null);
    }
}
