<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Amp\Success;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\CodeTransform\Domain\Refactor\ImportName;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentUri;

class ImportNameCommand implements Command
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

    /**
     * @var NameImporter
     */
    private $nameImporter;

    public function __construct(
        NameImporter $nameImporter,
        Workspace $workspace,
        TextEditConverter $textEditConverter,
        ClientApi $client
    ) {
        $this->workspace = $workspace;
        $this->textEditConverter = $textEditConverter;
        $this->client = $client;
        $this->nameImporter = $nameImporter;
    }

    public function __invoke(
        string $uri,
        int $offset,
        string $type,
        string $fqn,
        ?string $alias = null
    ): Promise {
        $document = $this->workspace->get($uri);

        try {
            $textEdits = $this->nameImporter->__invoke($document, $offset, $type, $fqn, $alias);
        } catch (TransformException $error) {
            $this->client->window()->showMessage()->warning($error->getMessage());
            return new Success(null);
        }

        if (null === $textEdits) {
            return new Success(null);
        }

        return $this->client->workspace()->applyEdit(new WorkspaceEdit([
            $uri => $this->textEditConverter->toLspTextEdits($textEdits, $document->text)
        ]), 'Import class');
    }
}
