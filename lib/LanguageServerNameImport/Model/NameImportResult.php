<?php

namespace Phpactor\Extension\LanguageServerNameImport\Model;

use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\LanguageServerProtocol\TextEdit as LspTextEdit;
use Throwable;

class NameImportResult
{
    /**
     * @var bool
     */
    private $success;

    /**
     * @var Throwable|null
     */
    private $error;

    /**
     * @var array<LspTextEdit>|null
     */
    private $textEdits;

    /**
     * @var NameImport|null
     */
    private $nameImport;

    private function __construct(
        bool $success,
        ?NameImport $nameImport,
        ?array $textEdits,
        ?Throwable $error
    ) {
        $this->success = $success;
        $this->nameImport = $nameImport;
        $this->textEdits = $textEdits;
        $this->error = $error;
    }

    public function hasTextEdits(): bool
    {
        return !empty($this->textEdits);
    }

    /**
     * @return array<LspTextEdit>|null
     */
    public function getTextEdits(): ?array
    {
        return $this->textEdits;
    }

    public function getNameImport(): ?NameImport
    {
        return $this->nameImport;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getError(): ?Throwable
    {
        return $this->error;
    }

    public static function createEmptyResult(): NameImportResult
    {
        return new NameImportResult(true, null, [], null);
    }

    /**
     * @param array<LspTextEdit> $textEdits
     * @param NameImport $nameImport
     * @return NameImportResult
     */
    public static function createResult(
        array $textEdits,
        NameImport $nameImport
    ): NameImportResult {
        return new NameImportResult(true, $nameImport, $textEdits, null);
    }

    public static function createErrorResult(Throwable $error): NameImportResult
    {
        return new NameImportResult(false, null, null, $error);
    }
}
