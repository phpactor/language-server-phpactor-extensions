<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\LspCommand;

use Amp\Promise;
use function Amp\Promise\wait;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\WorseReflection\Core\Exception\MethodCallNotFound;
use Phpactor\CodeTransform\Domain\Refactor\GenerateMethod;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\GenerateMethodCommand;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\RpcClient;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\ApplyWorkspaceEditResponse;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\TextDocument\TextDocumentEdits;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;
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

        $rpcClient = new class() implements RpcClient {
            /**
             * @var string
             */
            public $lastMethod;
            /**
             * @var array
             */
            public $lastParams;

            public function notification(string $method, array $params): void
            {
                // empty
            }
            public function request(string $method, array $params): Promise
            {
                $this->lastMethod = $method;
                $this->lastParams = $params;

                return new class implements Promise {
                    // @phpstan-ignore-next-line
                    public function onResolve(callable $onResolved): void
                    {
                        $obj = new stdClass();
                        $obj->result = new ApplyWorkspaceEditResponse(true, null, null);
                        $onResolved(null, $obj);
                    }
                };
            }
        };
        $apiClient = new ClientApi($rpcClient);

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
            $apiClient,
            $workspace->reveal(), // @phpstan-ignore-line
            $generateMethod->reveal() // @phpstan-ignore-line
        );
        
        wait($command->__invoke($uri, $offset));
        self::assertEquals('workspace/applyEdit', $rpcClient->lastMethod);
        self::assertEquals([
            'edit' => new WorkspaceEdit([
                $textEdits->uri()->path() => TextEditConverter::toLspTextEdits(
                    $textEdits->textEdits(),
                    $textDocumentItem->text
                )
            ]),
            'label' => 'Generate method'
        ], $rpcClient->lastParams);
    }
    /** @dataProvider provideExceptions */
    public function testFailureCall(Exception $exception): void
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

        $rpcClient = new class() implements RpcClient {
            /**
             * @var string
             */
            public $lastMethod;
            /**
             * @var array
             */
            public $lastParams;

            public function notification(string $method, array $params): void
            {
                $this->lastMethod = $method;
                $this->lastParams = $params;
            }
            public function request(string $method, array $params): Promise
            {
                return new class implements Promise {
                    // @phpstan-ignore-next-line
                    public function onResolve(callable $onResolved): void
                    {
                        $onResolved(null, null);
                    }
                };
            }
        };
        $apiClient = new ClientApi($rpcClient);

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
            $apiClient,
            $workspace->reveal(), // @phpstan-ignore-line
            $generateMethod->reveal() // @phpstan-ignore-line
        );
        
        wait($command->__invoke($uri, $offset));
        self::assertEquals('window/showMessage', $rpcClient->lastMethod);
        self::assertEquals([
            'type' => 2,
            'message' => $exception->getMessage()
        ], $rpcClient->lastParams);
    }

    public function provideExceptions(): array
    {
        return [
            'TransformException' => [ new TransformException('Error message!') ],
            'MethodCallNotFound' => [ new MethodCallNotFound('Error message!') ],
        ];
    }
}
