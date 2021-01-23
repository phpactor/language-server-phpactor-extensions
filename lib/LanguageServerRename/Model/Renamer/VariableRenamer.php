<?php

namespace Phpactor\Extension\LanguageServerRename\Model\Renamer;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerRename\Model\NodeUtils2;
use Phpactor\Extension\LanguageServerRename\Model\RenameLocationGroup;
use Phpactor\Extension\LanguageServerRename\Model\RenameLocationsProvider;
use Phpactor\Extension\LanguageServerRename\Model\RenameResult;
use Phpactor\Extension\LanguageServerRename\Model\Renamer2;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdit;
use Phpactor\TextDocument\TextEdits;

class VariableRenamer implements Renamer2
{
    /** @var Parser */
    private $parser;
    /** @var NodeUtils2 */
    private $nodeUtils;
    /** @var TextDocumentLocator */
    private $locator;
    /** @var Node */
    private $preparedNode;
    /** @var RenameLocationsProvider */
    private $locationsProvider;

    public function __construct(
        Parser $parser,
        NodeUtils2 $nodeUtils,
        TextDocumentLocator $locator,
        RenameLocationsProvider $locationsProvider
    ) {
        $this->parser = $parser;
        $this->nodeUtils = $nodeUtils;
        $this->locator = $locator;
        $this->locationsProvider = $locationsProvider;
    }

    public function prepareRename(TextDocument $textDocument, ByteOffset $offset): ?ByteOffsetRange
    {
        $rootNode = $this->parser->parseSourceFile((string)$textDocument);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());
        
        if (
            (
                $node instanceof Variable &&
                null === $node->getFirstAncestor(PropertyDeclaration::class)
            )
            || $node instanceof Parameter
        ) {
            $this->preparedNode = $node;
            [ $range ] = $this->nodeUtils->getNodeNameRanges($node);
            return $range;
        }
        return null;
    }
    /**
     * {@inheritDoc}
     */
    public function rename(TextDocument $textDocument, ByteOffset $offset, string $newName): Generator
    {
        [ $oldName ] = $this->nodeUtils->getNodeNameTexts($this->preparedNode);

        foreach ($this->locationsProvider->provideLocations($textDocument, $offset) as $locationGroup) {
            /** @var RenameLocationGroup $locationGroup */
            yield new RenameResult(
                $this->locationsToTextEdits($locationGroup->uri(), $locationGroup->locations(), $oldName, $newName),
                $locationGroup->uri()
            );
        }
    }
    /** @param Location[] $locations */
    private function locationsToTextEdits(TextDocumentUri $textDocumentUri, array $locations, string $oldName, string $newName): TextEdits
    {
        $textEdits = [];
        $rootNode = $this->parser->parseSourceFile($this->locator->get($textDocumentUri));
        foreach ($locations as $location) {
            $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt());
            $ranges = $this->nodeUtils->getNodeNameRanges($node);

            foreach ($ranges as $range) {
                $rangeText = $this->nodeUtils->getRangeText($range, $rootNode->getFileContents());

                if ($rangeText == $oldName) {
                    $textEdits[] = TextEdit::create($range->start(), $range->end()->toInt() - $range->start()->toInt(), $newName);
                    break;
                }
            }
        }
        return TextEdits::fromTextEdits($textEdits);
    }
}
