<?php

namespace Phpactor\Extension\LanguageServerBridge\Tests\Converter;

use LanguageServerProtocol\Position;
use LanguageServerProtocol\Range;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\TextEditConverter;
use Phpactor\LanguageServer\Core\Session\Workspace;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;
use Phpactor\Extension\LanguageServerBridge\Tests\IntegrationTestCase;
use LanguageServerProtocol\TextEdit as LspTextEdit;

class TextEditConverterTest extends IntegrationTestCase
{
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var TextEditConverter
     */
    private $converter;

    protected function setUp(): void
    {
        $this->workspace()->reset();
        $this->workspace = new Workspace();
        $this->converter = new TextEditConverter(new LocationConverter($this->workspace));
    }

    public function testConvertsTextEdits(): void
    {
        self::assertEquals([
            new LspTextEdit(new Range(
                new Position(1, 1),
                new Position(1, 4),
            ), 'foo'),
        ], $this->converter->toLspTextEdits(TextEdits::one(TextEdit::create(1, 3, 'foo'))));
    }
}
