<?php

namespace Phpactor\Extension\LanguageServerNameImport\Tests\Model;

use Exception;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\Extension\LanguageServerNameImport\Model\NameImportResult;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\TestUtils\PHPUnit\TestCase;

class NameImportResultTest extends TestCase
{
    public function testCreateEmptyResult(): void
    {
        $result = NameImportResult::createEmptyResult();

        self::assertTrue($result->isSuccess());
        self::assertNull($result->getNameImport());
        self::assertSame([], $result->getTextEdits());
        self::assertNull($result->getError());
    }

    public function testCreateErrorResult(): void
    {
        $error = new Exception('test');
        $result = NameImportResult::createErrorResult($error);

        self::assertFalse($result->isSuccess());
        self::assertNull($result->getNameImport());
        self::assertNull($result->getTextEdits());
        self::assertSame($error, $result->getError());
    }

    public function testCreateResult(): void
    {
        $textEdits = [
            new TextEdit(new Range(new Position(23, 23), new Position(23, 42)), 'Ahoi!')
        ];
        $nameImport = NameImport::forClass(self::class);
        $result = NameImportResult::createResult($textEdits, $nameImport);

        self::assertTrue($result->isSuccess());
        self::assertSame($nameImport, $result->getNameImport());
        self::assertSame($textEdits, $result->getTextEdits());
        self::assertNull($result->getError());
    }
}
