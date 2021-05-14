<?php

declare(strict_types=1);

namespace Phpactor\Extension\LanguageServerCompletion\Adapter\LanguageServerCodeTransform;

use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter\NameImporter;
use Phpactor\Extension\LanguageServerCodeTransform\Model\NameImporter\NameImporterResult;
use Phpactor\Extension\LanguageServerCompletion\Model\CompletionNameImporter;

class CodeTransformCompletionNameImporter implements CompletionNameImporter
{
    /**
     * @var NameImporter
     */
    private $nameImporter;

    public function __construct(NameImporter $nameImporter)
    {
        $this->nameImporter = $nameImporter;
    }

    public function import(
        string $uri,
        int $offset,
        string $type,
        string $fqn,
        ?string $alias = null
    ): NameImporterResult {
        return $this->nameImporter->import($uri, $offset, $type, $fqn, $alias);
    }
}
