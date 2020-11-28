<?php

namespace Phpactor\Extension\LanguageServerCompletion\Tests\Unit\Handler;

use Amp\Delayed;
use DTL\Invoke\Invoke;
use Generator;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use PHPUnit\Framework\TestCase;
use Phpactor\Completion\Core\Completor;
use Phpactor\Completion\Core\Range as PhpactorRange;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Completion\Core\TypedCompletorRegistry;
use Phpactor\Extension\LanguageServerCompletion\Handler\CompletionHandler;
use Phpactor\Extension\LanguageServerCompletion\Util\SuggestionNameFormatter;
use Phpactor\LanguageServer\LanguageServerTesterBuilder;
use Phpactor\LanguageServer\Test\LanguageServerTester;
use Phpactor\LanguageServer\Test\ProtocolFactory;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;

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

    public function testHandleAnIncompleteListOfSuggestions()
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

    public function testHandleSuggestionsWithRange()
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

    public function testCancelReturnsPartialResults()
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

    public function testHandleSuggestionsWithSnippets()
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

    public function testHandleSuggestionsWithSnippetsWhenClientDoesNotSupportIt()
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

    private function create(array $suggestions, bool $supportSnippets = true, bool $isIncomplete = false): LanguageServerTester
    {
        $completor = $this->createCompletor($suggestions, $isIncomplete);
        $registry = new TypedCompletorRegistry([
            'php' => $completor,
        ]);
        $builder = LanguageServerTesterBuilder::create();
        $tester = $builder->addHandler(new CompletionHandler(
            $builder->workspace(),
            $registry,
            new SuggestionNameFormatter(true),
            $supportSnippets,
            true
        ))->build();
        $tester->textDocument()->open(self::EXAMPLE_URI, self::EXAMPLE_TEXT);

        return $tester;
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
