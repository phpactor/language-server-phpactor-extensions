<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Handler\Unit;

use PHPUnit\Framework\TestCase;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\Extension\LanguageServerRename\Handler\RenameHandler;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\PrepareRenameParams;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\RenameParams;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit as LspTextEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use function Amp\Promise\wait;

class RenameHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testPrepareRename(): void
    {
        $uri1 = 'file:///test1';
        $documentText1 = 'example text 1';

        $workspace = $this->prophesize(Workspace::class);
        
        // @phpstan-ignore-next-line
        $workspace->get(Argument::any())->willReturn(TextDocumentItem::fromArray([
            'uri' => $uri1,
            'languageId' => 'php',
            'version' => 4,
            'text' => $documentText1,
        ]));

        $locator = $this->prophesize(TextDocumentLocator::class);
        // @phpstan-ignore-next-line
        $locator->get(Argument::any())->willReturn(
            TextDocumentBuilder::create($documentText1)->uri($uri1)->build()
        );

        $range = ByteOffsetRange::fromInts(1, 2);
        $renamer = $this->prophesize(Renamer::class);
        // @phpstan-ignore-next-line
        $renamer->getRenameRange(Argument::any(), Argument::any())->willReturn(
            $range
        );

        // @phpstan-ignore-next-line
        $handler = new RenameHandler($workspace->reveal(), $locator->reveal(), $renamer->reveal());
        $val = wait($handler->prepareRename(new PrepareRenameParams(
            new TextDocumentIdentifier($uri1),
            new Position(0, 1)
        )));
        $this->assertEquals(new Range(
            PositionConverter::byteOffsetToPosition($range->start(), $documentText1),
            PositionConverter::byteOffsetToPosition($range->end(), $documentText1)
        ), $val);
    }
    
    public function testPrepareRenameWithNull(): void
    {
        $uri1 = 'file:///test1';
        $documentText1 = 'example text 1';

        $workspace = $this->prophesize(Workspace::class);
        
        // @phpstan-ignore-next-line
        $workspace->get(Argument::any())->willReturn(TextDocumentItem::fromArray([
            'uri' => $uri1,
            'languageId' => 'php',
            'version' => 4,
            'text' => $documentText1,
        ]));

        $locator = $this->prophesize(TextDocumentLocator::class);
        // @phpstan-ignore-next-line
        $locator->get(Argument::any())->willReturn(
            TextDocumentBuilder::create($documentText1)->uri($uri1)->build()
        );

        $range = ByteOffsetRange::fromInts(1, 2);
        $renamer = $this->prophesize(Renamer::class);
        // @phpstan-ignore-next-line
        $renamer->getRenameRange(Argument::any(), Argument::any())->willReturn(
            null
        );

        // @phpstan-ignore-next-line
        $handler = new RenameHandler($workspace->reveal(), $locator->reveal(), $renamer->reveal());
        $val = wait($handler->prepareRename(new PrepareRenameParams(
            new TextDocumentIdentifier($uri1),
            new Position(0, 1)
        )));
        $this->assertEquals(null, $val);
    }
    
    public function testRename(): void
    {
        $uri1 = 'file:///test1';
        $documentText1 = 'example text 1';

        $uri2 = 'file:///test2';
        $documentText2 = 'example text 2';

        $workspace = $this->prophesize(Workspace::class);
        
        // @phpstan-ignore-next-line
        $workspace->has($uri1)->willReturn(true);
        // @phpstan-ignore-next-line
        $workspace->has($uri2)->willReturn(false);
        // @phpstan-ignore-next-line
        $workspace->get($uri1)->willReturn(TextDocumentItem::fromArray([
            'uri' => $uri1,
            'languageId' => 'php',
            'version' => 4,
            'text' => $documentText1,
        ]));

        $locator = $this->prophesize(TextDocumentLocator::class);
        // @phpstan-ignore-next-line
        $locator->get(TextDocumentUri::fromString($uri1))->willReturn(
            TextDocumentBuilder::create($documentText1)->uri($uri1)->build()
        );
        // @phpstan-ignore-next-line
        $locator->get(TextDocumentUri::fromString($uri2))->willReturn(
            TextDocumentBuilder::create($documentText2)->uri($uri2)->build()
        );

        $results = [
            new RenameResult(
                TextEdits::fromTextEdits(
                    [
                        TextEdit::create(0, 1, 'newText'),
                        TextEdit::create(1, 1, 'newText'),
                    ]
                ),
                TextDocumentUri::fromString($uri1)
            ),
            new RenameResult(
                TextEdits::fromTextEdits(
                    [
                        TextEdit::create(2, 1, 'newText'),
                    ]
                ),
                TextDocumentUri::fromString($uri2)
            ),
        ];
        $renamer = $this->prophesize(Renamer::class);
        
        // @phpstan-ignore-next-line
        $renamer->rename(Argument::any(), Argument::any(), Argument::any())->willYield($results);

        // @phpstan-ignore-next-line
        $handler = new RenameHandler($workspace->reveal(), $locator->reveal(), $renamer->reveal());
        $val = wait($handler->rename(new RenameParams(
            new TextDocumentIdentifier($uri1),
            new Position(0, 1),
            'newText'
        )));
        
        $this->assertEquals(
            new WorkspaceEdit(
                [
                    new TextDocumentEdit(
                        new VersionedTextDocumentIdentifier($uri1, 4),
                        [
                            new LspTextEdit(
                                new Range(
                                    PositionConverter::intByteOffsetToPosition(0, $documentText1),
                                    PositionConverter::intByteOffsetToPosition(1, $documentText1),
                                ),
                                'newText'
                            ),
                            new LspTextEdit(
                                new Range(
                                    PositionConverter::intByteOffsetToPosition(1, $documentText1),
                                    PositionConverter::intByteOffsetToPosition(2, $documentText1),
                                ),
                                'newText'
                            )
                        ]
                    ),
                    new TextDocumentEdit(
                        new VersionedTextDocumentIdentifier($uri2, 0),
                        [
                            new LspTextEdit(
                                new Range(
                                    PositionConverter::intByteOffsetToPosition(2, $documentText2),
                                    PositionConverter::intByteOffsetToPosition(3, $documentText2),
                                ),
                                'newText'
                            ),
                        ]
                    )
                ],
                []
            ),
            $val
        );
    }
}
