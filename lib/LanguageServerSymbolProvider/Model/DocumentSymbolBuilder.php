<?php

namespace Phpactor\Extension\LanguageServerSymbolProvider\Model;

use Phpactor\LanguageServerProtocol\DocumentSymbol;

interface DocumentSymbolBuilder
{
    /**
     * @return array<DocumentSymbol>
     */
    public function build(string $source): array;
}
