<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ClassConstDeclaration;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\NamespaceAliasingClause;
use Microsoft\PhpParser\Node\NamespaceUseClause;
use Microsoft\PhpParser\Node\NamespaceUseGroupClause;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\Statement\InlineHtml;
use Microsoft\PhpParser\Node\Statement\InterfaceDeclaration;
use Microsoft\PhpParser\Node\Statement\NamespaceDefinition;
use Microsoft\PhpParser\Node\Statement\TraitDeclaration;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\ResolvedName;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\RenameFile;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use function mb_strlen;
use function mb_substr;

class Renamer
{
    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var DefinitionLocator
     */
    private $definitionLocator;
    /**
     * @var ReferenceFinder
     */
    private $finder;
    /**
     * @var ClientApi
     */
    private $clientApi;
    /**
     * @var int
     */
    private $timeoutSeconds = 10;
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var NodeUtils
     */
    private $nodeUtils;

    public function __construct(
        Workspace $workspace,
        Parser $parser,
        ReferenceFinder $finder,
        DefinitionLocator $definitionLocator,
        ClientApi $clientApi,
        NodeUtils $nodeUtils
    ) {
        $this->workspace = $workspace;
        $this->parser = $parser;
        $this->definitionLocator = $definitionLocator;
        $this->finder = $finder;
        $this->clientApi = $clientApi;
        $this->nodeUtils = $nodeUtils;
    }

    public function prepareRename(TextDocumentItem $textDocument, Position $position): ?Range
    {
        $offset = PositionConverter::positionToByteOffset($position, $textDocument->text);
        $node = $this->getDocumentRootNode($textDocument, $offset);
        
        if ($this->canRenameNode($node)) {
            return $this->nodeUtils->getNodeNameRange($node);
        }

        return null;
    }

    public function rename(TextDocumentItem $textDocument, Position $position, string $newName): ?WorkspaceEdit
    {
        $offset = PositionConverter::positionToByteOffset($position, $textDocument->text);
        $node = $this->getDocumentRootNode($textDocument, $offset);
        
        $phpactorDocument = TextDocumentBuilder::create($textDocument->text)
            ->uri($textDocument->uri)
            ->language($textDocument->languageId ?? 'php')
            ->build();

        
        if ($this->canRenameNode($node)) {
            return $this->renameNode($phpactorDocument, $offset, $node, $this->nodeUtils->getNodeNameText($node), $newName);
        }
        
        return null;
    }

    private function getDocumentRootNode(TextDocumentItem $textDocument, ByteOffset $offset): Node
    {
        $rootNode = $this->parser->parseSourceFile($textDocument->text);
        return $rootNode->getDescendantNodeAtPosition($offset->toInt());
    }
    
    private function renameNode(TextDocument $phpactorDocument, ByteOffset $offset, Node $node, string $oldName, string $newName): ?WorkspaceEdit
    {
        if (empty($oldName)) {
            return null;
        }
        
        $locations = [];
        $oldFqn = null;
        try {
            $location = $this->definitionLocator->locateDefinition($phpactorDocument, $offset);
            [ $fixedLocation, $oldFqn ] = $this->getDefinitionNameLocation($location, $oldName);
            $locations[] = $fixedLocation;
        } catch (CouldNotLocateDefinition $notFound) {
            // ignore the missing definition
        }
        
        $start = microtime(true);
        $count = 1;
        foreach ($this->finder->findReferences($phpactorDocument, $offset) as $location) {
            if (!$location->isSurely()) {
                continue;
            }
            
            $locations[] = $location->location();

            if ($count++ % 100 === 0 && $count > 0) {
                $this->clientApi->window()->showMessage()->info(sprintf(
                    '... scanned %s references confirmed %s ...',
                    $count - 1,
                    count($locations)
                ));
            }

            if (microtime(true) - $start > $this->timeoutSeconds) {
                $this->clientApi->window()->showMessage()->info(sprintf(
                    'Reference find stopped, %s/%s references confirmed but took too long (%s/%s seconds).',
                    count($locations),
                    $count,
                    number_format(microtime(true) - $start, 2),
                    $this->timeoutSeconds
                ));
                return $this->locationsToWorkspaceEdit($locations, $oldFqn, $oldName, $newName);
            }
        }
        
        $this->clientApi->window()->showMessage()->info(sprintf(
            'Found %s reference(s) to be renamed.',
            count($locations)
        ));

        return $this->locationsToWorkspaceEdit($locations, $oldFqn, $oldName, $newName);
    }
    /**
     * Given the defintion location returns the location of the name token and the
     * FQN of the type if one is being renamed (classes, interfaces or traits)
     */
    private function getDefinitionNameLocation(DefinitionLocation $location, string $oldName): array // NOSONAR
    {
        $documentContents = $this->getDocumentText($location->uri());
        $rootNode = (new Parser())->parseSourceFile($documentContents);
        $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt());
        
        if ($node instanceof InlineHtml) {
            // that must be a class or interface declaration (or something at the root level), move the offset by one to the right
            $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt() + 1);
        }

        if ($node instanceof QualifiedName && $node->parent instanceof Parameter) {
            return [
                new Location($location->uri(), ByteOffset::fromInt($node->parent->variableName->start)),
                null
            ];
        } elseif ($node instanceof Parameter) {
            return [
                new Location($location->uri(), ByteOffset::fromInt($node->variableName->start)),
                null
            ];
        } elseif (
            $node instanceof ClassDeclaration
            || $node instanceof InterfaceDeclaration
            || $node instanceof TraitDeclaration
        ) {
            $namespacePrefix = null;
            if (($namespace = $node->getNamespaceDefinition()) !== null) {
                /** @var NamespaceDefinition $namespace */
                $namespacePrefix = "{$namespace->name->getNamespacedName()->getFullyQualifiedNameText()}\\";
            }
            
            return [
                new Location($location->uri(), ByteOffset::fromInt($node->name->start)),
                "{$namespacePrefix}{$node->name->getText($documentContents)}"
            ];
        } elseif ($node instanceof PropertyDeclaration) {
            foreach ($node->propertyElements->getElements() as $nodeOrToken) {
                /** @var Node|Token $nodeOrToken */
                if ($nodeOrToken instanceof Variable
                    && $nodeOrToken->name instanceof Token
                    && $nodeOrToken->getName() == $oldName
                ) {
                    return [
                        new Location($location->uri(), ByteOffset::fromInt($nodeOrToken->name->start)),
                        null
                    ];
                }
            }
        } elseif ($node instanceof ClassConstDeclaration) {
            foreach ($node->constElements->getElements() as $element) {
                if ($element instanceof ConstElement && $element->getName() == $oldName) {
                    return [
                        new Location($location->uri(), ByteOffset::fromInt($element->name->start)),
                        null
                    ];
                }
            }
        }
        
        return [
            new Location($location->uri(), $location->offset()),
            null
        ];
    }

    private function locationsToWorkspaceEdit(array $locations, ?string $oldFqn, string $oldName, string $newName): WorkspaceEdit
    {
        // group locations by uri
        $locationsByUri = [];
        foreach ($locations as $location) {
            /** @var Location $location */
            $uri = (string)$location->uri();
            if (!isset($locationsByUri[$uri])) {
                $locationsByUri[$uri] = [];
            }
            $locationsByUri[$uri][] = $location;
        }

        $documentEdits = [];
        foreach ($locationsByUri as $uri => $locations) {
            list($textEdits, $rename) = $this->documentLocationsToTextEdits($uri, $locations, $oldFqn, $oldName, $newName);
            $documentEdits[] = new TextDocumentEdit(
                new VersionedTextDocumentIdentifier($uri, $this->getDocumentVersion($uri)),
                $textEdits
            );
            if ($rename !== null) {
                $documentEdits[] = $rename;
            }
        }

        return new WorkspaceEdit(null, $documentEdits);
    }

    private function documentLocationsToTextEdits(string $documentUri, array $locations, ?string $oldFqn, string $oldName, string $newName): array
    {
        $edits = [];
        $rename = null;
        $documentContent = $this->getDocumentText($documentUri);
        $rootNode = $this->parser->parseSourceFile($documentContent);

        $edits = $this->findNamespaceUseClauses($rootNode, $oldFqn, $oldName, $newName);

        foreach ($locations as $location) {
            /** @var Location $location */
            $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt());
            
            if (($r = $this->renameFileIfNeeded($node, $documentUri, $oldName, $newName)) !== null) {
                $rename = $r;
            }

            $nodeNameText = $this->nodeUtils->getNodeNameText($node, $oldName);
            if ($nodeNameText !== $oldName) {
                continue;
            }
            
            $position = $this->nodeUtils->getNodeNameStartPosition($node, $oldName);
            
            if ($position !== null) {
                $edits[] = new TextEdit(
                    new Range(
                        $position,
                        new Position($position->line, $position->character + mb_strlen($oldName))
                    ),
                    $newName
                );
            }
        }

        return [ $this->sortEditsByStartLocation($edits), $rename ];
    }

    private function sortEditsByStartLocation(array $edits): array
    {
        usort($edits, function (TextEdit $e1, TextEdit $e2) {
            $r1 = $e1->range->start->line * 1000 + $e1->range->start->character;
            $r2 = $e2->range->start->line * 1000 + $e2->range->start->character;
            if ($r1 > $r2) {
                return 1;
            }
            if ($r1 < $r2) {
                return -1;
            }
            return 0;
        });
        return $edits;
    }

    private function findNamespaceUseClauses(Node $rootNode, ?string $oldFqn, string $oldName, string $newName): array
    {
        $edits = [];
        $documentContent = $rootNode->getFileContents();
        foreach ($rootNode->getDescendantNodes() as $node) {
            if (false === $node instanceof NamespaceUseClause) {
                continue;
            }
            
            /** @var NamespaceUseClause $node */
            if (
                null === $node->groupClauses
                && ($edit = $this->getQualifiedNameEdit(
                    $node->namespaceName,
                    $node->namespaceAliasingClause,
                    '',
                    $oldFqn,
                    $oldName,
                    $newName
                )) !== null
            ) {
                $edits[] = $edit;
                continue;
            }

            if (null !== $node->groupClauses) {
                foreach ($node->groupClauses->getElements() as $groupClause) {
                    /** @var NamespaceUseGroupClause $groupClause */
                    if ((
                        $edit = $this->getQualifiedNameEdit(
                            $groupClause->namespaceName,
                            $groupClause->namespaceAliasingClause,
                            ResolvedName::buildName($node->namespaceName->nameParts, $documentContent)->getFullyQualifiedNameText(),
                            $oldFqn,
                            $oldName,
                            $newName
                        )
                    ) !== null
                    ) {
                        $edits[] = $edit;
                    }
                }
            }
        }
        return $edits;
    }

    private function getQualifiedNameEdit(QualifiedName $qualifiedName, ?NamespaceAliasingClause $alias, string $prefix, ?string $oldFqn, string $oldName, string $newName): ?TextEdit
    {
        if (!empty($prefix) && mb_substr($prefix, -1) != "\\") {
            $prefix .= "\\";
        }
        $documentContent = $qualifiedName->getFileContents();
        $fqn = ResolvedName::buildName($qualifiedName->nameParts, $documentContent)->getFullyQualifiedNameText();
        $lastPartText = $qualifiedName->getLastNamePart()->getText($documentContent);
        
        if ($prefix.$fqn === $oldFqn) {
            if ($lastPartText === $oldName) {
                return new TextEdit(
                    $this->nodeUtils->getTokenRange($qualifiedName->getLastNamePart(), $documentContent),
                    $newName
                );
            }
            if (null !== $alias && $alias->name->getText($documentContent) === $oldName) {
                return new TextEdit(
                    $this->nodeUtils->getTokenRange($alias->name, $documentContent),
                    $newName
                );
            }
        }

        return null;
    }

    private function renameFileIfNeeded(Node $node, string $documentUri, string $oldName, string $newName): ?RenameFile
    {
        if (
            false === $node instanceof ClassDeclaration
            && false === $node instanceof InterfaceDeclaration
            && false === $node instanceof TraitDeclaration
        ) {
            return null;
        }
        
        $parts = explode("/", $documentUri);
        $fileName = array_pop($parts);
        $extension = null;
        if (($lastDot = strrpos($fileName, ".")) !== false) {
            $extension = mb_substr($fileName, $lastDot);
            $fileName = mb_substr($fileName, 0, $lastDot);
        }
        if ($fileName == $oldName) {
            return new RenameFile(
                'rename',
                $documentUri,
                implode("/", $parts) ."/{$newName}{$extension}"
            );
        }

        return null;
    }

    private function getDocumentVersion(string $uri): int
    {
        return $this->workspace->has($uri) ? $this->workspace->get($uri)->version : 0;
    }

    private function getDocumentText(string $uri): string
    {
        if ($this->workspace->has($uri)) {
            return $this->workspace->get($uri)->text;
        }

        $contents = @file_get_contents($uri);

        if (false === $contents) {
            return "";
        }

        return $contents;
    }

    private function canRenameNode(Node $node): bool
    {
        return
            $node instanceof ClassDeclaration
            || $node instanceof MethodDeclaration
            || $node instanceof InterfaceDeclaration
            || $node instanceof TraitDeclaration
            || $node instanceof QualifiedName
            || $node instanceof ConstElement
            || ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token)
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class))
            || ($node instanceof MemberAccessExpression && $node->memberName instanceof Token)
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class) === null)
            || $node instanceof Parameter
            ;
    }
}
