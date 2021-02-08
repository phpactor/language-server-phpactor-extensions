<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Model\Renamer;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\ChainRenamer;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer\InMemoryRenamer;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdits;
use function iterator_to_array;

class ChainRenamerTest extends TestCase
{
    public function testReturnsNullWithNoRenamers(): void
    {
        $this->assertRename([], null, []);
    }

    public function testGetFirstNonNullRename(): void
    {
        $range1 = new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
        $results1 = [
            new RenameResult(TextEdits::none(), TextDocumentUri::fromString('/foo/bar'))
        ];
        $renamer1 = new InMemoryRenamer($range1, $results1);
        $renamer2 = new InMemoryRenamer(null, []);

        $this->assertRename([$renamer2, $renamer1], $range1, $results1);
        $this->assertRename([$renamer1, $renamer2], $range1, $results1);
    }

    public function testGetRenameRangeTwoMatches(): void
    {
        $range1 = new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
        $range2 = new ByteOffsetRange(ByteOffset::fromInt(0), ByteOffset::fromInt(1));
        $results2 = [
            new RenameResult(TextEdits::none(), TextDocumentUri::fromString('/foo/bar'))
        ];
        $renamer1 = new InMemoryRenamer($range1, []);
        $renamer2 = new InMemoryRenamer($range2, $results2);

        $this->assertRename([$renamer2, $renamer1], $range2, $results2);
    }

    private function assertRename(array $renamers, ?ByteOffsetRange $range1, array $results): void
    {
        $textDocument = TextDocumentBuilder::create('text')->uri('file:///test1')->build();
        $byteOffset = ByteOffset::fromInt(0);

        $this->assertSame(
            $range1,
            $this->createRenamer($renamers)->getRenameRange($textDocument, $byteOffset),
            'Returns expected range',
        );
        $this->assertSame(
            $results,
            iterator_to_array($this->createRenamer($renamers)->rename($textDocument, $byteOffset, 'foobar')),
            'Returns expected results',
        );
    }

    private function createRenamer(array $renamers): ChainRenamer
    {
        return new ChainRenamer($renamers);
    }
}
