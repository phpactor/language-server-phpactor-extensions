<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\Worse;

use Generator;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;

class RenameLocationsProvider
{
    /**
     * @var ReferenceFinder
     */
    private $finder;
    /**
     * @var DefinitionLocator
     */
    private $definitionLocator;

    public function __construct(
        ReferenceFinder $finder,
        DefinitionLocator $definitionLocator
    ) {
        $this->finder = $finder;
        $this->definitionLocator = $definitionLocator;
    }
    
    public function provideLocations(TextDocument $textDocument, ByteOffset $offset): Generator
    {
        $definitionLocation = null;
        try {
            $definitionLocation = $this->definitionLocator->locateDefinition($textDocument, $offset);
        } catch (CouldNotLocateDefinition $notFound) {
            // ignore the missing definition
        }
        
        $hadReferences = false;
        $usedDefinition = false;
        $currentDocumentUri = null;
        $currentDocumentLocations = [];
        $documentRootNode = null;
        foreach ($this->finder->findReferences($textDocument, $offset) as $reference) {
            if (!$reference->isSurely()) {
                continue;
            }

            $hadReferences = true;

            if ((string)$currentDocumentUri !== (string)$reference->location()->uri()) {
                if (!empty($currentDocumentLocations) && null !== $currentDocumentUri) {
                    yield new RenameLocationGroup(
                        $currentDocumentUri,
                        $currentDocumentLocations
                    );
                }
                $currentDocumentUri = $reference->location()->uri();
                $currentDocumentLocations = [];
                if ($definitionLocation !== null && $reference->location()->uri() == $definitionLocation->uri()) {
                    $currentDocumentLocations[] = $definitionLocation;
                    $usedDefinition = true;
                }
            }
            
            $currentDocumentLocations[] = $reference->location();
        }

        if ($hadReferences && !empty($currentDocumentLocations) && null !== $currentDocumentUri) {
            yield new RenameLocationGroup(
                $currentDocumentUri,
                $currentDocumentLocations
            );
        }

        if ((!$hadReferences || !$usedDefinition) && null !== $definitionLocation) {
            yield new RenameLocationGroup(
                $definitionLocation->uri(),
                [$definitionLocation]
            );
        }
    }
}
