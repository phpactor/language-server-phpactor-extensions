<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Amp\Promise;
use Amp\Success;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\GenerateMethod;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServerProtocol\ApplyWorkspaceEditResponse;
use Phpactor\LanguageServer\Core\Command\Command;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\WorseReflection\Core\Exception\MethodCallNotFound;

class GenerateMethodCommand implements Command
{
    public const NAME  = 'generate_method';
    /**
     * @var ClientApi
     */
    private $clientApi;
    /**
     * @var GenerateMethod
     */
    private $generateMethod;
    /**
     * @var Workspace
     */
    private $workspace;

    public function __construct(
        ClientApi $clientApi,
        Workspace $workspace,
        GenerateMethod $generateMethod
    ) {
        $this->clientApi = $clientApi;
        $this->generateMethod = $generateMethod;
        $this->workspace = $workspace;
    }
    /**
     * @return Promise<?ApplyWorkspaceEditResponse>
     */
    public function __invoke(string $uri, int $offset): Promise
    {
        $document = $this->workspace->get($uri);
        $sourceCode = SourceCode::fromStringAndPath(
            $document->text,
            TextDocumentUri::fromString($document->uri)->path()
        );

        $textEdits = [];
        try {
            $textEdits = $this->generateMethod->generateMethod($sourceCode, $offset);
        } catch (TransformException $error) {
			$this->clientApi->window()->showMessage()->warning($error->getMessage());
            return new Success(null);
        } catch (MethodCallNotFound $error) {
			$this->clientApi->window()->showMessage()->warning($error->getMessage());
            return new Success(null);
		}

        return $this->clientApi->workspace()->applyEdit(new WorkspaceEdit([
            $uri => TextEditConverter::toLspTextEdits($textEdits, $document->text)
        ]), 'Generate method');
    }
}
