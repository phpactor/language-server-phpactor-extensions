<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Amp\Success;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentUri;

class ImportNameCommand
{
    public const NAME = 'name_import';

    /**
     * @var ImportName
     */
    private $importName;

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
        ImportName $importName,
        Workspace $workspace,
        TextEditConverter $textEditConverter,
        ClientApi $client
    ) {
        $this->importName = $importName;
        $this->workspace = $workspace;
        $this->textEditConverter = $textEditConverter;
        $this->client = $client;
    }

    public function __invoke(string $uri, int $offset, string $type, string $fqn, ?string $alias = null): Promise
    {
        $document = $this->workspace->get($uri);
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
        } catch (NameAlreadyImportedException $error) {
            if ($error->existingName() === $fqn) {
                return new Success(null);
            }

            $name = FullyQualifiedName::fromString($fqn);
            $prefix = 'Aliased';
            if (isset($name->toArray()[0])) {
                $prefix = $name->toArray()[0];
            }

            return $this->__invoke($uri, $offset, $type, $fqn, $prefix . $error->name());
        } catch (AliasAlreadyUsedException $error) {
            $prefix = 'Aliased';
            return $this->__invoke($uri, $offset, $type, $fqn, $prefix . $error->name());
        } catch (TransformException $error) {
            $this->client->window()->showMessage()->warning($error->getMessage());
            return new Success(null);
        }

        /** @phpstan-ignore-next-line */
        return $this->client->workspace()->applyEdit(new WorkspaceEdit([
            $uri => $this->textEditConverter->toLspTextEdits($textEdits, $document->text)
        ]), 'Import class');
    }
}
