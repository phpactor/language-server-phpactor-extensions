<?php

declare(strict_types=1);

namespace Phpactor\Extension\LanguageServerCompletion\Model;

use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter\NameImporterResult;

interface CompletionNameImporter
{
    public function import(
        string $uri,
        int $offset,
        string $type,
        string $fqn,
        ?string $alias = null
    ): NameImporterResult;
}
