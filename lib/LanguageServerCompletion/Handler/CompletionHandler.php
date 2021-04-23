<?php

namespace Phpactor\Extension\LanguageServerCompletion\Handler;

use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Delayed;
use Amp\Promise;
use Phpactor\CodeBuilder\Domain\Builder\SourceCodeBuilder;
use Phpactor\CodeBuilder\Domain\Code;
use Phpactor\CodeBuilder\Domain\Updater;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\InsertTextFormat;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\SignatureHelpOptions;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\Completion\Core\TypedCompletorRegistry;
use Phpactor\Extension\LanguageServerCompletion\Util\PhpactorToLspCompletionType;
use Phpactor\Extension\LanguageServerCompletion\Util\SuggestionNameFormatter;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\TextEdit as LspTextEdit;
use Phpactor\TextDocument\TextDocumentBuilder;

class CompletionHandler implements Handler, CanRegisterCapabilities
{
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
     * @var Updater
     */
    private $updater;

    public function __construct(
        Workspace $workspace,
        TypedCompletorRegistry $registry,
        SuggestionNameFormatter $suggestionNameFormatter,
        Updater $updater,
        bool $supportSnippets,
        bool $provideTextEdit = false
    ) {
        $this->registry = $registry;
        $this->provideTextEdit = $provideTextEdit;
        $this->workspace = $workspace;
        $this->updater = $updater;
        $this->suggestionNameFormatter = $suggestionNameFormatter;
        $this->supportSnippets = $supportSnippets;
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
            $byteOffset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);
            $suggestions = $this->registry->completorForType(
                $languageId
            )->complete(
                TextDocumentBuilder::create($textDocument->text)->language($languageId)->uri($textDocument->uri)->build(),
                $byteOffset
            );

            $items = [];
            $isIncomplete = false;
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
                    'sortText' => $this->sortText($suggestion),
                    'textEdit' => $this->textEdit($suggestion, $textDocument),
                    'additionalTextEdits' => $this->additionalTextEdits($suggestion, $textDocument),
                    'insertTextFormat' => $insertTextFormat
                ]);

                try {
                    $token->throwIfRequested();
                } catch (CancelledException $cancellation) {
                    $isIncomplete = true;
                    break;
                }
                yield new Delayed(0);
            }

            $isIncomplete = $isIncomplete || !$suggestions->getReturn();

            return new CompletionList($isIncomplete, $items);
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
                PositionConverter::byteOffsetToPosition($range->start(), $textDocument->text),
                PositionConverter::byteOffsetToPosition($range->end(), $textDocument->text),
            ),
            $suggestion->name()
        );
    }

    /**
     * @param Suggestion $suggestion
     * @param TextDocumentItem $textDocument
     * @return array<LspTextEdit>|null
     */
    private function additionalTextEdits(Suggestion $suggestion, TextDocumentItem $textDocument): ?array
    {
        if (!$suggestion->nameImport()) {
            return null;
        }

        if (!in_array($suggestion->type(), [ 'class', 'function'])) {
            return null;
        }

        $textEdits = $this->updater->textEditsFor(
            SourceCodeBuilder::create()->use($suggestion->nameImport())->build(),
            Code::fromString($textDocument->text)
        );

        return TextEditConverter::toLspTextEdits($textEdits, $textDocument->text);
    }

    private function formatShortDescription(Suggestion $suggestion): string
    {
        $prefix = '';
        if ($suggestion->classImport()) {
            $prefix = '↓ ';
        }

        return $prefix . $suggestion->shortDescription();
    }

    private function sortText(Suggestion $suggestion): ?string
    {
        if (null === $suggestion->priority()) {
            return null;
        }

        return sprintf('%04s-%s', $suggestion->priority(), $suggestion->name());
    }
}
