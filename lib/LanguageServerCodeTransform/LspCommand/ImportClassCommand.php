<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Amp\Success;
use LanguageServerProtocol\WorkspaceEdit;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\ClassAlreadyImportedException;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\Name\FullyQualifiedName;
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
    ) {
        $this->importClass = $importClass;
        $this->workspace = $workspace;
        $this->textEditConverter = $textEditConverter;
        $this->client = $client;
    }

    public function __invoke(string $uri, int $offset, string $fqn, ?string $alias = null): Promise
    {
        $document = $this->workspace->get($uri);
        $sourceCode = SourceCode::fromStringAndPath(
            $document->text,
            TextDocumentUri::fromString($document->uri)->path()
        );

        try {
            if ($alias) {
                // alias is not nullable but it can be null...
                $textEdits = $this->importClass->importClass($sourceCode, $offset, $fqn, $alias);
            } else {
                $textEdits = $this->importClass->importClass($sourceCode, $offset, $fqn);
            }
        } catch (ClassAlreadyImportedException $error) {
            if ($error->existingName() === $fqn) {
                return new Success(null);
            }
            $fqn = FullyQualifiedName::fromString($fqn);

            return $this->__invoke($uri, $offset, $fqn, 'Aliased' . $fqn->head()->__toString());
        } catch (TransformException $error) {
            $this->client->window()->showMessage()->info($error->getMessage());
            return new Success(null);
        }

        /** @phpstan-ignore-next-line */
        return $this->client->workspace()->applyEdit(new WorkspaceEdit([
            $uri => $this->textEditConverter->toLspTextEdits($textEdits, $document->text)
        ]), 'Import class');
    }
}
