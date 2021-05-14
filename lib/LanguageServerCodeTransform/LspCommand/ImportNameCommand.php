<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Amp\Success;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter\NameImporter;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;

class ImportNameCommand implements Command
{
    public const NAME = 'name_import';

    /**
     * @var NameImporter
     */
    private $importName;

    /**
     * @var ClientApi
     */
    private $client;

    public function __construct(NameImporter $importName, ClientApi $client)
    {
        $this->importName = $importName;
        $this->client = $client;
    }

    public function __invoke(
        string $uri,
        int $offset,
        string $type,
        string $fqn,
        ?string $alias = null
    ): Promise {
        $result = $this->importName->import($uri, $offset, $type, $fqn, $alias);

        if ($result->isSuccess()) {
            return $this->client->workspace()->applyEdit(
                new WorkspaceEdit([
                    $uri => $result->getTextEdits()
                ]),
                'Import class'
            );
        }

        $error = $result->getError();
        $this->client->window()->showMessage()->warning($error->getMessage());
        return new Success(null);
    }
}
