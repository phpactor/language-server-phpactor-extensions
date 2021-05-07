<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\LspCommand;

use Phpactor\LanguageServer\Core\Server\RpcClient\TestRpcClient;
use Phpactor\WorseReflection\Core\Exception\CouldNotResolveNode;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\WorseReflection\Core\Exception\MethodCallNotFound;
use Phpactor\CodeTransform\Domain\Refactor\GenerateMethod;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\GenerateMethodCommand;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\TextDocument\TextDocumentEdits;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Exception;

class GenerateMethodCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessfulCall(): void
    {
        $uri = 'file:///file.php';
        $offset = 5;
        $textDocumentItem = new TextDocumentItem($uri, 'php', 1, '<?php ');
        $textDocumentUri = TextDocumentUri::fromString($textDocumentItem->uri);
        $sourceCode = SourceCode::fromStringAndPath(
            $textDocumentItem->text,
            $textDocumentUri->path()
        );
        $textEdits = new TextDocumentEdits(
            $textDocumentUri,
            new TextEdits(TextEdit::create(5, 1, 'test'))
        );

        $rpcClient = TestRpcClient::create();

        $workspace = $this->prophesize(Workspace::class);
        // @phpstan-ignore-next-line
        $workspace->get($uri)
            ->willReturn($textDocumentItem);

        $generateMethod = $this->prophesize(GenerateMethod::class);
        // @phpstan-ignore-next-line
        $generateMethod->generateMethod($sourceCode, $offset)
            ->shouldBeCalled()
            ->willReturn($textEdits);

        $command = new GenerateMethodCommand(
            new ClientApi($rpcClient),
            $workspace->reveal(), // @phpstan-ignore-line
            $generateMethod->reveal() // @phpstan-ignore-line
        );
        
        $command->__invoke($uri, $offset);
        
        $applyEdit = $rpcClient->transmitter()->filterByMethod('workspace/applyEdit')->shiftRequest();

        self::assertNotNull($applyEdit);
        self::assertEquals([
            'edit' => new WorkspaceEdit([
                $textEdits->uri()->path() => TextEditConverter::toLspTextEdits(
                    $textEdits->textEdits(),
                    $textDocumentItem->text
                )
            ]),
            'label' => 'Generate method'
        ], $applyEdit->params);
    }
    /** @dataProvider provideExceptions */
    public function testFailedCall(Exception $exception): void
    {
        $uri = 'file:///file.php';
        $offset = 5;
        $textDocumentItem = new TextDocumentItem($uri, 'php', 1, '<?php ');
        $textDocumentUri = TextDocumentUri::fromString($textDocumentItem->uri);
        $sourceCode = SourceCode::fromStringAndPath(
            $textDocumentItem->text,
            $textDocumentUri->path()
        );
        $textEdits = new TextDocumentEdits(
            $textDocumentUri,
            new TextEdits(TextEdit::create(5, 1, 'test'))
        );

        $rpcClient = TestRpcClient::create();

        $workspace = $this->prophesize(Workspace::class);
        // @phpstan-ignore-next-line
        $workspace->get($uri)
            ->willReturn($textDocumentItem);

        $generateMethod = $this->prophesize(GenerateMethod::class);
        // @phpstan-ignore-next-line
        $generateMethod->generateMethod($sourceCode, $offset)
            ->shouldBeCalled()
            ->willThrow($exception);

        $command = new GenerateMethodCommand(
            new ClientApi($rpcClient),
            $workspace->reveal(), // @phpstan-ignore-line
            $generateMethod->reveal() // @phpstan-ignore-line
        );
        
        $command->__invoke($uri, $offset);
        $showMessage = $rpcClient->transmitter()->filterByMethod('window/showMessage')->shiftNotification();

        self::assertNotNull($showMessage);
        self::assertEquals([
            'type' => 2,
            'message' => $exception->getMessage()
        ], $showMessage->params);
    }

    public function provideExceptions(): array
    {
        return [
            TransformException::class => [ new TransformException('Error message!') ],
            MethodCallNotFound::class => [ new MethodCallNotFound('Error message!') ],
            CouldNotResolveNode::class => [ new CouldNotResolveNode('Error message!') ],
        ];
    }
}
