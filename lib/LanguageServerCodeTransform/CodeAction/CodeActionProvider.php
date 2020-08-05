<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\CodeAction;

use Generator;
use Phpactor\LanguageServerProtocol\Range;

interface CodeActionProvider
{
    /**
     * @return Generator<CodeAction>
     */
    public function actionsFor(Range $range): Generator;
}
