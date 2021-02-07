<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Bridge\Unit;

use Generator;
use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Bridge\ChainRenamer;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdits;
use function iterator_to_array;

class ChainRenamerTest extends TestCase
{
    public function testGetRenameRange_OneMatch(): void
    {
        $renamer1 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                return;
                yield;
            }
        };

        $renamer2 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return null;
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                return;
                yield;
            }
        };

        $textDocument = TextDocumentBuilder::create('text')->uri('file:///test1')->build();
        $renamer = new ChainRenamer([$renamer2, $renamer1]);
        $this->assertEquals(
            new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1)),
            $renamer->getRenameRange($textDocument, ByteOffset::fromInt(0))
        );
		$renamer = new ChainRenamer([$renamer1, $renamer2]);
        $this->assertEquals(
            new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1)),
            $renamer->getRenameRange($textDocument, ByteOffset::fromInt(0))
        );
    }

	public function testGetRenameRange_TwoMatches(): void
    {
        $renamer1 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                return;
                yield;
            }
        };

        $renamer2 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return new ByteOffsetRange(ByteOffset::fromInt(2), ByteOffset::fromInt(3));
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                return;
                yield;
            }
        };

        $textDocument = TextDocumentBuilder::create('text')->uri('file:///test1')->build();
        $renamer = new ChainRenamer([$renamer2, $renamer1]);
        $this->assertEquals(
            new ByteOffsetRange(ByteOffset::fromInt(2), ByteOffset::fromInt(3)),
            $renamer->getRenameRange($textDocument, ByteOffset::fromInt(0))
        );
		$renamer = new ChainRenamer([$renamer1, $renamer2]);
        $this->assertEquals(
            new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1)),
            $renamer->getRenameRange($textDocument, ByteOffset::fromInt(0))
        );
    }

    public function testRename_OneMatch(): void
    {
        $renamer1 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                yield new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test1'));
            }
        };

        $renamer2 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return null;
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                yield new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test2'));
            }
        };

        $textDocument = TextDocumentBuilder::create('text')->uri('file:///test1')->build();
        $renamer = new ChainRenamer([$renamer2, $renamer1]);
        $this->assertEquals(
            [new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test1'))],
            iterator_to_array($renamer->rename($textDocument, ByteOffset::fromInt(0), "newName"), true)
        );
		$renamer = new ChainRenamer([$renamer1, $renamer2]);
        $this->assertEquals(
            [new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test1'))],
            iterator_to_array($renamer->rename($textDocument, ByteOffset::fromInt(0), "newName"), true)
        );
    }

	public function testRename_TwoMatches(): void
    {
        $renamer1 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                yield new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test1'));
            }
        };

        $renamer2 = new class() implements Renamer {
            public function getRenameRange(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
            {
                return new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
            }
            
            public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
            {
                yield new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test2'));
            }
        };

        $textDocument = TextDocumentBuilder::create('text')->uri('file:///test1')->build();
        $renamer = new ChainRenamer([$renamer2, $renamer1]);
        $this->assertEquals(
            [new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test2'))],
            iterator_to_array($renamer->rename($textDocument, ByteOffset::fromInt(0), "newName"), true)
        );
		$renamer = new ChainRenamer([$renamer1, $renamer2]);
        $this->assertEquals(
            [new RenameResult(TextEdits::fromTextEdits([]), TextDocumentUri::fromString('file:///test1'))],
            iterator_to_array($renamer->rename($textDocument, ByteOffset::fromInt(0), "newName"), true)
        );
    }
}
