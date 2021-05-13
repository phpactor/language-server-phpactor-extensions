<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Tests\Unit\Model\ImportName;

use Exception;
use Generator;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport;
use Phpactor\Extension\LanguageServerCodeTransform\Model\ImportName\ImportNameResult;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\TestUtils\PHPUnit\TestCase;

class ImportNameResultTest extends TestCase
{
    public function testCreateEmptyResult(): void
    {
        $result = ImportNameResult::createEmptyResult();

        self::assertTrue($result->isSuccess());
        self::assertNull($result->getNameImport());
        self::assertNull($result->getTextEdits());
        self::assertNull($result->getError());
        self::assertFalse($result->isSuccessAndHasAliasedNameImport());
    }

    public function testCreateErrorResult(): void
    {
        $error = new Exception('test');
        $result = ImportNameResult::createErrorResult($error);

        self::assertFalse($result->isSuccess());
        self::assertNull($result->getNameImport());
        self::assertNull($result->getTextEdits());
        self::assertSame($error, $result->getError());
        self::assertFalse($result->isSuccessAndHasAliasedNameImport());
    }

    public function provideCreateResultTestData(): Generator
    {
        $textEdits = [
            new TextEdit(
                new Range(
                    new Position(23, 23),
                    new Position(23, 42)
                ),
                'TestText'
            )
        ];

        yield 'alias' => [
            [],
            NameImport::forClass(self::class, self::class . 'Alias'),
            true
        ];

        yield 'no alias' => [
            $textEdits,
            NameImport::forClass(self::class),
            false
        ];
    }

    /**
     * @dataProvider provideCreateResultTestData
     */
    public function testCreateResult(
        array $textEdits,
        NameImport $nameImport,
        bool $expectedIsSuccessAndHasAliasedNameImport
    ): void {
        $result = ImportNameResult::createResult($nameImport, $textEdits);

        self::assertTrue($result->isSuccess());
        self::assertSame($nameImport, $result->getNameImport());
        self::assertSame($textEdits, $result->getTextEdits());
        self::assertNull($result->getError());
        self::assertSame($expectedIsSuccessAndHasAliasedNameImport, $result->isSuccessAndHasAliasedNameImport());
    }
}
