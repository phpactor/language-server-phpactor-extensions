<?php

namespace LanguageServerNameImport\Service;

use Exception;
use Generator;
use Phpactor\CodeTransform\Domain\Exception\TransformException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\AliasAlreadyUsedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameImport as ImportClassNameImport;
use Phpactor\CodeTransform\Domain\Refactor\ImportName as RefactorImportName;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\Extension\LanguageServerNameImport\Service\NameImport;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit as LspTextEdit;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use Prophecy\Prophecy\ObjectProphecy;
use RuntimeException;

class NameImportServiceTest extends TestCase
{
    const EXAMPLE_CONTENT = 'hello this is some text';
    const EXAMPLE_PATH = '/foobar.php';
    const EXAMPLE_OFFSET = 12;
    const EXAMPLE_PATH_URI = 'file:///foobar.php';

    /**
     * @var ObjectProphecy<RefactorImportName>
     */
    private $importNameProphecy;

    /**
     * @var ObjectProphecy<Workspace>
     */
    private $workspaceProphecy;

    /**
     * @var ObjectProphecy<TextDocumentItem>
     */
    private $documentProphecy;

    /**
     * @var array<LspTextEdit>
     */
    private $lspTextEdits;

    /**
     * @var TextEdits
     */
    private $textEdits;

    /**
     * @var SourceCode
     */
    private $sourceCode;

    /**
     * @var ByteOffset
     */
    private $byteOffset;

    /**
     * @var ObjectProphecy<AliasAlreadyUsedException>
     */
    private $aliasAlreadyUsedExceptionProphecy;

    /**
     * @var ObjectProphecy<NameAlreadyImportedException>
     */
    private $nameAlreadyImportedExceptionProphecy;

    /**
     * @var NameImport
     */
    private $subject;

    protected function setUp(): void
    {
        $this->documentProphecy = $this->prophesize(TextDocumentItem::class);
        $this->documentProphecy->text = self::EXAMPLE_CONTENT;
        $this->documentProphecy->uri = self::EXAMPLE_PATH_URI;

        $this->workspaceProphecy = $this->prophesize(Workspace::class);
        $this->workspaceProphecy->get(self::EXAMPLE_PATH_URI)
            ->willReturn($this->documentProphecy->reveal());

        $this->importNameProphecy = $this->prophesize(RefactorImportName::class);
        $this->byteOffset = ByteOffset::fromInt(self::EXAMPLE_OFFSET);
        $this->aliasAlreadyUsedExceptionProphecy = $this->prophesize(AliasAlreadyUsedException::class);
        $this->nameAlreadyImportedExceptionProphecy = $this->prophesize(
            NameAlreadyImportedException::class
        );

        $this->textEdits = TextEdits::one(
            TextEdit::create(23, 6, 'huhuhu')
        );

        $this->lspTextEdits = TextEditConverter::toLspTextEdits($this->textEdits, self::EXAMPLE_CONTENT);

        $this->sourceCode = SourceCode::fromStringAndPath(
            self::EXAMPLE_CONTENT,
            TextDocumentUri::fromString(self::EXAMPLE_PATH_URI)->path()
        );

        $this->subject = new NameImport(
            $this->importNameProphecy->reveal(),
            $this->workspaceProphecy->reveal()
        );
    }

    public function provideTestImportData(): Generator
    {
        yield 'function' => [
            '\in_array',
            'function',
            ImportClassNameImport::forFunction('\in_array'),
        ];

        yield 'class' => [
            self::class,
            'class',
            ImportClassNameImport::forClass(self::class),
        ];
    }

    /**
     * @dataProvider provideTestImportData
     */
    public function testImport(
        string $fqn,
        string $importType,
        ImportClassNameImport $importClassNameImport
    ): void {
        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            $importClassNameImport
        )->willReturn($this->textEdits);

        $result = $this->subject->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            $importType,
            $fqn
        );

        self::assertTrue($result->isSuccess());
        self::assertEquals($importClassNameImport, $result->getNameImport());
        self::assertEquals($this->lspTextEdits, $result->getTextEdits());
        self::assertNull($result->getError());
    }

    public function testImportTransformException(): void
    {
        $error = new TransformException('error!!');

        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            ImportClassNameImport::forClass(Exception::class),
        )->willThrow($error);

        $result = $this->subject->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            Exception::class
        );

        self::assertFalse($result->isSuccess());
        self::assertNull($result->getNameImport());
        self::assertNull($result->getTextEdits());
        self::assertSame($error, $result->getError());
    }

    public function testImportAliasAlreadyUsedException(): void
    {
        $this->aliasAlreadyUsedExceptionProphecy->name()
            ->willReturn(Exception::class);

        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            ImportClassNameImport::forClass(Exception::class),
        )->willThrow($this->aliasAlreadyUsedExceptionProphecy->reveal());

        $aliasedNameImport = ImportClassNameImport::forClass(Exception::class, 'AliasedException');

        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            $aliasedNameImport,
        )->willReturn($this->textEdits);

        $result = $this->subject->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            Exception::class
        );

        self::assertTrue($result->isSuccess());
        self::assertEquals($aliasedNameImport, $result->getNameImport());
        self::assertEquals($this->lspTextEdits, $result->getTextEdits());
        self::assertNull($result->getError());
    }

    public function testImportNameAlreadyImportedExceptionExisting(): void
    {
        $this->nameAlreadyImportedExceptionProphecy->existingName()
            ->willReturn(Exception::class);

        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            ImportClassNameImport::forClass(Exception::class),
        )->willThrow($this->nameAlreadyImportedExceptionProphecy->reveal());

        $result = $this->subject->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            Exception::class
        );

        self::assertTrue($result->isSuccess());
        self::assertNull($result->getNameImport());
        self::assertSame([], $result->getTextEdits());
        self::assertNull($result->getError());
    }

    public function testImportNameAlreadyImportedExceptionNotExisting(): void
    {
        $this->nameAlreadyImportedExceptionProphecy->existingName()
            ->willReturn(RuntimeException::class);
        $this->nameAlreadyImportedExceptionProphecy->name()
            ->willReturn(Exception::class);

        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            ImportClassNameImport::forClass(Exception::class),
        )->willThrow($this->nameAlreadyImportedExceptionProphecy->reveal());

        $aliasedNameImport = ImportClassNameImport::forClass(Exception::class, 'ExceptionException');

        $this->importNameProphecy->importName(
            $this->sourceCode,
            $this->byteOffset,
            $aliasedNameImport,
        )->willReturn($this->textEdits);

        $result = $this->subject->import(
            self::EXAMPLE_PATH_URI,
            self::EXAMPLE_OFFSET,
            'class',
            Exception::class
        );

        self::assertTrue($result->isSuccess());
        self::assertEquals($aliasedNameImport, $result->getNameImport());
        self::assertEquals($this->lspTextEdits, $result->getTextEdits());
        self::assertNull($result->getError());
    }
}
