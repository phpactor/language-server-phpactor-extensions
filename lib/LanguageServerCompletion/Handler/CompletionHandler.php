<?php

namespace Phpactor\Extension\LanguageServerCompletion\Handler;

use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Delayed;
use Amp\Promise;
use Phpactor\Extension\LanguageServerBridge\Converter\OffsetConverter;
use Phpactor\LanguageServerProtocol\Command;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\InsertTextFormat;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\SignatureHelpOptions;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\Completion\Core\Completor;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Completion\Core\TypedCompletorRegistry;
use Phpactor\Extension\LanguageServerCodeTransform\LanguageServerCodeTransformExtension;
use Phpactor\Extension\LanguageServerCodeTransform\LspCommand\ImportNameCommand;
use Phpactor\Extension\LanguageServerCompletion\Util\PhpactorToLspCompletionType;
use Phpactor\Extension\LanguageServerCompletion\Util\SuggestionNameFormatter;
use Phpactor\Extension\LanguageServer\Helper\OffsetHelper;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;

class CompletionHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Completor
     */
    private $completor;

    /**
     * @var TypedCompletorRegistry
     */
    private $registry;

    /**
     * @var bool
     */
    private $provideTextEdit;

    /**
     * @var SuggestionNameFormatter
     */
    private $suggestionNameFormatter;

    /**
     * @var Workspace
     */
    private $workspace;

    /**
     * @var bool
     */
    private $supportSnippets;

    /**
     * @var OffsetConverter
     */
    private $converter;

    public function __construct(
        Workspace $workspace,
        TypedCompletorRegistry $registry,
        SuggestionNameFormatter $suggestionNameFormatter,
        OffsetConverter $converter,
        bool $supportSnippets,
        bool $provideTextEdit = false
    ) {
        $this->registry = $registry;
        $this->provideTextEdit = $provideTextEdit;
        $this->workspace = $workspace;
        $this->suggestionNameFormatter = $suggestionNameFormatter;
        $this->supportSnippets = $supportSnippets;
        $this->converter = $converter;
    }

    public function methods(): array
    {
        return [
            'textDocument/completion' => 'completion',
        ];
    }

    public function completion(CompletionParams $params, CancellationToken $token): Promise
    {
        return \Amp\call(function () use ($params, $token) {
            $textDocument = $this->workspace->get($params->textDocument->uri);

            $languageId = $textDocument->languageId ?: 'php';
            $byteOffset = $this->converter->toOffset($params->position, $textDocument->text);
            $suggestions = $this->registry->completorForType(
                $languageId
            )->complete(
                TextDocumentBuilder::create($textDocument->text)->language($languageId)->uri($textDocument->uri)->build(),
                $byteOffset
            );

            $items = [];
            foreach ($suggestions as $suggestion) {
                $name = $this->suggestionNameFormatter->format($suggestion);
                $insertText = $name;
                $insertTextFormat = InsertTextFormat::PLAIN_TEXT;

                if ($this->supportSnippets) {
                    $insertText = $suggestion->snippet() ?: $name;
                    $insertTextFormat = $suggestion->snippet()
                        ? InsertTextFormat::SNIPPET
                        : InsertTextFormat::PLAIN_TEXT
                    ;
                }

                $items[] = CompletionItem::fromArray([
                    'label' => $name,
                    'kind' => PhpactorToLspCompletionType::fromPhpactorType($suggestion->type()),
                    'detail' => $this->formatShortDescription($suggestion),
                    'documentation' => $suggestion->documentation(),
                    'insertText' => $insertText,
                    'textEdit' => $this->textEdit($suggestion, $textDocument),
                    'command' => $this->command($textDocument->uri, $byteOffset, $suggestion),
                    'insertTextFormat' => $insertTextFormat
                ]);

                try {
                    $token->throwIfRequested();
                } catch (CancelledException $cancellation) {
                    break;
                }
                yield new Delayed(0);
            }

            return new CompletionList(true, $items);
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->completionProvider = new CompletionOptions([':', '>', '$']);
        $capabilities->signatureHelpProvider = new SignatureHelpOptions(['(', ',']);
    }

    private function textEdit(Suggestion $suggestion, TextDocumentItem $textDocument): ?TextEdit
    {
        if (false === $this->provideTextEdit) {
            return null;
        }

        $range = $suggestion->range();

        if (!$range) {
            return null;
        }

        return new TextEdit(
            new Range(
                OffsetHelper::offsetToPosition($textDocument->text, $range->start()->toInt()),
                OffsetHelper::offsetToPosition($textDocument->text, $range->end()->toInt())
            ),
            $suggestion->name()
        );
    }

    private function command(string $uri, ByteOffset $offset, Suggestion $suggestion): ?Command
    {
        if (!$suggestion->nameImport()) {
            return null;
        }

        if (!in_array($suggestion->type(), [ 'class', 'function'])) {
            return null;
        }

        return new Command(
            'Import class',
            ImportNameCommand::NAME,
            [$uri, $offset->toInt(), $suggestion->type(), $suggestion->nameImport()]
        );
    }

    private function formatShortDescription(Suggestion $suggestion): string
    {
        $prefix = '';
        if ($suggestion->classImport()) {
            $prefix = '↓ ';
        }

        return $prefix . $suggestion->shortDescription();
    }
}
