<?php

namespace Phpactor\Extension\LanguageServerCompletion\Tests\Unit\Handler;

use Amp\Delayed;
use DTL\Invoke\Invoke;
use Generator;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\Completion\Core\Completor;
use Phpactor\Completion\Core\Range as PhpactorRange;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Completion\Core\TypedCompletorRegistry;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter\NameImporterResult;
use Phpactor\Extension\LanguageServerCompletion\Handler\CompletionHandler;
use Phpactor\Extension\LanguageServerCompletion\Model\CompletionNameImporter;
use Phpactor\Extension\LanguageServerCompletion\Util\SuggestionNameFormatter;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use PHPUnit\Framework\TestCase;

class CompletionHandlerTest extends TestCase
{
    const EXAMPLE_URI = 'file:///test';
    const EXAMPLE_TEXT = 'hello';

    public function testHandleNoSuggestions(): void
    {
        $tester = $this->create([]);
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertInstanceOf(CompletionList::class, $response->result);
        $this->assertEquals([], $response->result->items);
        $this->assertFalse($response->result->isIncomplete);
    }

    public function testHandleACompleteListOfSuggestions(): void
    {
        $tester = $this->create([
            Suggestion::create('hello'),
            Suggestion::create('goodbye'),
        ]);
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertInstanceOf(CompletionList::class, $response->result);
        $this->assertEquals([
            self::completionItem('hello', null),
            self::completionItem('goodbye', null),
        ], $response->result->items);
        $this->assertFalse($response->result->isIncomplete);
    }

    public function testHandleAnIncompleteListOfSuggestions(): void
    {
        $tester = $this->create([
            Suggestion::create('hello'),
            Suggestion::create('goodbye'),
        ], true, true);
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertInstanceOf(CompletionList::class, $response->result);
        $this->assertEquals([
            self::completionItem('hello', null),
            self::completionItem('goodbye', null),
        ], $response->result->items);
        $this->assertTrue($response->result->isIncomplete);
    }

    public function testHandleSuggestionsWithRange(): void
    {
        $tester = $this->create([
            Suggestion::createWithOptions('hello', [ 'range' => PhpactorRange::fromStartAndEnd(1, 2)]),
        ]);
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertEquals([
            self::completionItem('hello', null, ['textEdit' => new TextEdit(
                new Range(new Position(0, 1), new Position(0, 2)),
                'hello'
            )])
        ], $response->result->items);
        $this->assertFalse($response->result->isIncomplete);
    }

    public function testSuggestionWithImport(): void
    {
        $tester = $this->create(
            [
                Suggestion::createWithOptions(
                    'hello',
                    [
                        'type'        => 'class',
                        'name_import' => '\Foo\Bar'
                    ]
                ),
            ],
            true,
            false,
            [
                [new TextEdit(new Range(new Position(0, 0), new Position(0, 4)), 'world')]
            ]
        );
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position'     => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertEquals(
            [
                self::completionItem(
                    'hello',
                    null,
                    [
                        'kind' => 7,
                        'detail' => '↓ ',
                        'additionalTextEdits' => [
                            TextEdit::fromArray([
                                'newText' => 'world',
                                'range' => Range::fromArray([
                                    'start' => Position::fromArray(['line' => 0, 'character' => 0]),
                                    'end' => Position::fromArray(['line' => 0, 'character' => 4]),
                                ])
                            ])
                        ]
                    ]
                )
            ],
            $response->result->items
        );
        $this->assertFalse($response->result->isIncomplete);
    }

    public function testCancelReturnsPartialResults(): void
    {
        $tester = $this->create(
            array_map(function () {
                return Suggestion::createWithOptions('hello', [ 'range' => PhpactorRange::fromStartAndEnd(1, 2)]);
            }, range(0, 10000))
        );
        $response = $tester->request(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ],
            1
        );
        $responses =\Amp\Promise\wait(\Amp\Promise\all([
            $response,
            \Amp\call(function () use ($tester) {
                yield new Delayed(10);
                $tester->cancel(1);
            })
        ]));

        $this->assertGreaterThan(1, count($responses[0]->result->items));
        $this->assertTrue($responses[0]->result->isIncomplete);
    }

    public function testHandleSuggestionsWithSnippets(): void
    {
        $tester = $this->create([
            Suggestion::createWithOptions('hello', [
                'type' => Suggestion::TYPE_METHOD,
                'label' => 'hello'
            ]),
            Suggestion::createWithOptions('goodbye', [
                'type' => Suggestion::TYPE_METHOD,
                'snippet' => 'goodbye()',
            ]),
            Suggestion::createWithOptions('$var', [
                'type' => Suggestion::TYPE_VARIABLE,
            ]),
        ]);
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertEquals([
            self::completionItem('hello', 2),
            self::completionItem('goodbye', 2, ['insertText' => 'goodbye()', 'insertTextFormat' => 2]),
            self::completionItem('var', 6),
        ], $response->result->items);
        $this->assertFalse($response->result->isIncomplete);
    }

    public function testHandleSuggestionsWithSnippetsWhenClientDoesNotSupportIt(): void
    {
        $tester = $this->create([
            Suggestion::createWithOptions('hello', [
                'type' => Suggestion::TYPE_METHOD,
                'label' => 'hello'
            ]),
            Suggestion::createWithOptions('goodbye', [
                'type' => Suggestion::TYPE_METHOD,
                'snippet' => 'goodbye()',
            ]),
            Suggestion::createWithOptions('$var', [
                'type' => Suggestion::TYPE_VARIABLE,
            ]),
        ], false);
        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );
        $this->assertEquals([
            self::completionItem('hello', 2),
            self::completionItem('goodbye', 2),
            self::completionItem('var', 6),
        ], $response->result->items);
        $this->assertFalse($response->result->isIncomplete);
    }

    public function testHandleSuggestionsWithProiority(): void
    {
        $tester = $this->create([
            Suggestion::createWithOptions('hello', [
                'type' => Suggestion::TYPE_METHOD,
                'label' => 'hello',
                'priority' => Suggestion::PRIORITY_HIGH
            ]),
            Suggestion::createWithOptions('goodbye', [
                'type' => Suggestion::TYPE_METHOD,
                'snippet' => 'goodbye()',
                'priority' => Suggestion::PRIORITY_LOW
            ]),
            Suggestion::createWithOptions('$var', [
                'type' => Suggestion::TYPE_VARIABLE,
            ]),
        ], false);

        $response = $tester->requestAndWait(
            'textDocument/completion',
            [
                'textDocument' => ProtocolFactory::textDocumentIdentifier(self::EXAMPLE_URI),
                'position' => ProtocolFactory::position(0, 0)
            ]
        );

        $this->assertEquals([
            self::completionItem('hello', 2, [
                'sortText' => '0064-hello',
            ]),
            self::completionItem('goodbye', 2, [
                'sortText' => '0255-goodbye',
            ]),
            self::completionItem('var', 6),
        ], $response->result->items);
        $this->assertFalse($response->result->isIncomplete);
    }

    private static function completionItem(
        string $label,
        ?int $type,
        array $data = []
    ): CompletionItem {
        return Invoke::new(CompletionItem::class, \array_merge([
            'label' => $label,
            'kind' => $type,
            'detail' => '',
            'documentation' => '',
            'insertText' => $label,
            'insertTextFormat' => 1,
        ], $data));
    }

    private function create(
        array $suggestions,
        bool $supportSnippets = true,
        bool $isIncomplete = false,
        array $textEditsFor = []
    ): LanguageServerTester {
        $completor = $this->createCompletor($suggestions, $isIncomplete);
        $registry = new TypedCompletorRegistry([
            'php' => $completor,
        ]);
        $builder = LanguageServerTesterBuilder::create();
        $tester = $builder->addHandler(new CompletionHandler(
            $builder->workspace(),
            $registry,
            new SuggestionNameFormatter(true),
            $this->createImportName($suggestions, $textEditsFor),
            $supportSnippets,
            true
        ))->build();
        $tester->textDocument()->open(self::EXAMPLE_URI, self::EXAMPLE_TEXT);

        return $tester;
    }

    /**
     * @param array<Suggestion> $suggestions
     * @param array $textEditsFor
     * @return CompletionNameImporter
     */
    private function createImportName(array $suggestions, array $textEditsFor): CompletionNameImporter
    {
        $results = [];

        foreach ($suggestions as $i => $suggestion) {
            /** @var Suggestion $suggestion */
            $textEdits = $textEditsFor[$i] ?? null;

            if ($suggestion->type() === 'function') {
                $nameImport = NameImport::forFunction($suggestion->name());
            } else {
                $nameImport = NameImport::forClass($suggestion->name());
            }

            $results[] = NameImporterResult::createResult($nameImport, $textEdits);
        }

        $importNameMock = $this->getMockBuilder(CompletionNameImporter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $importNameMock->method('import')
            ->willReturnOnConsecutiveCalls(...$results);

        return $importNameMock;
    }

    private function createCompletor(array $suggestions, bool $isIncomplete = false): Completor
    {
        return new class($suggestions, $isIncomplete) implements Completor {
            private $suggestions;
            private $isIncomplete;
            public function __construct(array $suggestions, bool $isIncomplete)
            {
                $this->suggestions = $suggestions;
                $this->isIncomplete = $isIncomplete;
            }

            public function complete(TextDocument $source, ByteOffset $offset): Generator
            {
                foreach ($this->suggestions as $suggestion) {
                    yield $suggestion;

                    // simulate work
                    usleep(100);
                }

                return !$this->isIncomplete;
            }
        };
    }
}
