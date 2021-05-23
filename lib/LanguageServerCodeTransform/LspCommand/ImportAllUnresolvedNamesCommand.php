<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\LspCommand;

use Phpactor\Indexer\Model\SearchClient;
use Phpactor\WorseReflection\Core\Reflector\FunctionReflector;
use Phpactor\CodeTransform\Domain\Helper\UnresolvableClassNameFinder;

class ImportAllUnresolvedNamesCommand
{
    /**
     * @var UnresolvableClassNameFinder
     */
    private $finder;

    /**
     * @var FunctionReflector
     */
    private $functionReflector;

    /**
     * @var SearchClient
     */
    private $client;

    /**
     * @var bool
     */
    private $importGlobals;

    public function __construct(
        UnresolvableClassNameFinder $finder,
        FunctionReflector $functionReflector,
        SearchClient $client,
        bool $importGlobals = false
    ) {
        $this->finder = $finder;
        $this->functionReflector = $functionReflector;
        $this->client = $client;
        $this->importGlobals = $importGlobals;
    }

    public function __invoke(
        string $uri
    )
    {
    }
}
