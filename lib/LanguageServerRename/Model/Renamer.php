<?php

namespace Phpactor\Extension\LanguageServerRename\Model;

use Amp\Delayed;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\Token;
use Phpactor\Extension\LanguageServerBridge\Converter\Exception\CouldNotLoadFileContents;
use Phpactor\Extension\LanguageServerBridge\Converter\LocationConverter;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentEdit;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\LanguageServerProtocol\VersionedTextDocumentIdentifier;
use Phpactor\LanguageServerProtocol\WorkspaceEdit;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;

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
     * @var LocationConverter
     */
    private $locationConverter;
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

    public function __construct(
        Workspace $workspace,
        Parser $parser,
        ReferenceFinder $finder,
        DefinitionLocator $definitionLocator,
        LocationConverter $locationConverter,
        ClientApi $clientApi
    ) {
        $this->parser = $parser;
        $this->definitionLocator = $definitionLocator;
        $this->finder = $finder;
        $this->locationConverter = $locationConverter;
        $this->clientApi = $clientApi;
        $this->workspace = $workspace;
    }

    public function prepareRename(TextDocumentItem $textDocument, Position $position): ?Range
    {
        $offset = PositionConverter::positionToByteOffset($position, $textDocument->text);
        
        $rootNode = $this->parser->parseSourceFile($textDocument->text);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());

        $range = null;
        if ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof Parameter) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof Variable) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof MethodDeclaration) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof ClassDeclaration) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof ConstElement) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof ScopedPropertyAccessExpression) {
            $range = $this->getNodeNameRange($node);
        } elseif ($node instanceof MemberAccessExpression) {
            $range = $this->getNodeNameRange($node);
        } else {
            dump("Cannot rename: ". get_class($node));
        }

        return $range;
    }

    public function rename(TextDocumentItem $textDocument, Position $position, string $newName): ?WorkspaceEdit
    {
        $offset = PositionConverter::positionToByteOffset($position, $textDocument->text);
        
        $rootNode = $this->parser->parseSourceFile($textDocument->text);
        $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());
        
        $phpactorDocument = TextDocumentBuilder::create(
            $textDocument->text
        )->uri(
            $textDocument->uri
        )->language(
            $textDocument->languageId ?? 'php'
        )->build();

        if (
            $node instanceof MethodDeclaration ||
            $node instanceof ClassDeclaration ||
            $node instanceof ConstElement ||
            ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) ||
            ($node instanceof MemberAccessExpression && $node->memberName instanceof Token) ||
            ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token)
        ) {
            return $this->renameClassOrMemberSymbol($phpactorDocument, $offset, $node, $this->getNodeName($node, $phpactorDocument), $newName);
        }

        return null;
    }
    
    private function renameClassOrMemberSymbol(TextDocument $phpactorDocument, ByteOffset $offset, Node $node, string $oldName, string $newName): ?WorkspaceEdit
    {
        if (empty($oldName)) {
            return null;
        }
        $locations = [];
        try {
            $potentialLocation = $this->definitionLocator->locateDefinition($phpactorDocument, $offset);
            $locations[] = new Location($potentialLocation->uri(), $potentialLocation->offset());
        } catch (CouldNotLocateDefinition $notFound) {
        }
        
        $start = microtime(true);
        $count = 0;
        foreach ($this->finder->findReferences($phpactorDocument, $offset) as $potentialLocation) {
            if (!$potentialLocation->isSurely()) {
                continue;
            }
            
            $locations[] = $potentialLocation->location();

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
                return $this->toWorkspaceEdit($locations, $oldName, $newName);
            }

            // if ($count++ % 10) {
            //     // give other co-routines a chance
            //     yield new Delayed(0);
            // }
        }
        
        $this->clientApi->window()->showMessage()->info(sprintf(
            'Found %s reference(s) to be renamed.',
            count($locations)
        ));

        return $this->toWorkspaceEdit($locations, $oldName, $newName);
    }

    private function toWorkspaceEdit(array $locations, string $oldName, string $newName): WorkspaceEdit
    {
        // group locations by uri
        $locationsByUri = [];
        foreach ($locations as $location) {
            $uri = (string)$location->uri();
            if (!isset($locationsByUri[$uri])) {
                $locationsByUri[$uri] = [];
            }
            $locationsByUri[$uri][] = $location;
        }

        $documentEdits = [];
        foreach ($locationsByUri as $uri => $locations) {
            $edits = [];
            $documentContent = $this->loadText($uri);
            $rootNode = $this->parser->parseSourceFile($documentContent);
            foreach ($locations as $location) {
                /** @var Location $location */
                $node = $rootNode->getDescendantNodeAtPosition($location->offset()->toInt());
                $position = $this->getNodeNamePosition($node, $oldName);
                $temp = PositionConverter::intByteOffsetToPosition($location->offset()->toInt(), $documentContent);
                $temp2 = PositionConverter::intByteOffsetToPosition($node->getStart(), $documentContent);

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

            $version = $this->workspace->has($uri) ? $this->workspace->get($uri)->version : 0;
            $documentEdits[] = new TextDocumentEdit(
                new VersionedTextDocumentIdentifier($uri, $version),
                $edits
            );
        }

        return new WorkspaceEdit(null, $documentEdits);
    }
    
    private function getNodeName(Node $node, TextDocument $phpactorDocument): ?string
    {
        if (
            $node instanceof MethodDeclaration
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class))
            || $node instanceof ConstElement
            || $node instanceof Parameter
            || $node instanceof Variable
        ) {
            return $node->getName();
        } elseif ($node instanceof ClassDeclaration) {
            $name = $node->name->getText((string)$phpactorDocument);
            return is_string($name) ? $name : null;
        } elseif (
            ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token)
            || ($node instanceof MemberAccessExpression && $node->memberName instanceof Token)
        ) {
            $memberName = $node->memberName;
            /** @var Token $memberName */
            $name = $memberName->getText((string)$phpactorDocument);
            return is_string($name) ? $name : null;
        }
        return null;
    }

    private function getNodeNamePosition(Node $node, string $name): ?Position
    {
        $range = $this->getNodeNameRange($node, $name);
        return ($range !== null) ? $range->start : null;
    }

    private function getNodeNameRange(Node $node, ?string $name = null): ?Range
    {
        $fileContents = $node->getRoot()->fileContents;
        if ($node instanceof MethodDeclaration) {
            return $this->getTokenRange($node->name, $fileContents);
        } elseif ($node instanceof ClassDeclaration) {
            return $this->getTokenRange($node->name, $fileContents);
        } elseif ($node instanceof QualifiedName && ($nameToken = $node->getLastNamePart()) !== null) {
            return $this->getTokenRange($nameToken, $fileContents);
        } elseif ($node instanceof ScopedPropertyAccessExpression && $node->memberName instanceof Token) {
            return $this->getTokenRange($node->memberName, $fileContents);
        } elseif ($node instanceof Variable && $node->name instanceof Token && $node->getFirstAncestor(PropertyDeclaration::class)) {
            return $this->fixVariableNameRange(
                $node,
                $this->getTokenRange($node->name, $fileContents),
                $fileContents
            );
        } elseif ($node instanceof ConstElement) {
            return $this->getTokenRange($node->name, $fileContents);
        } elseif ($node instanceof Parameter) {
            $range = $this->getTokenRange($node->variableName, $fileContents);
            $range->start->character++; // compensate for the dollar
            return $range;
        } elseif ($node instanceof Variable && $node->name instanceof Token) {
            return $this->fixVariableNameRange(
                $node,
                $this->getTokenRange($node->name, $fileContents),
                $fileContents
            );
        } elseif ($node instanceof MemberAccessExpression && $node->memberName instanceof Token) {
            return $this->getTokenRange($node->memberName, $fileContents);
        } elseif ($node instanceof PropertyDeclaration) {
            foreach ($node->propertyElements->getElements() as $nodeOrToken) {
                /** @var Node|Token $nodeOrToken */
                if ($nodeOrToken instanceof Variable && $nodeOrToken->name instanceof Token && !empty($name) && $nodeOrToken->getName() == $name) {
                    return $this->fixVariableNameRange(
                        $nodeOrToken,
                        $this->getTokenRange($nodeOrToken->name, $fileContents),
                        $fileContents
                    );
                }
            }
            return null;
        } else {
            dump("Cannot get node name position for node: ". get_class($node));
        }
        return null;
    }

    private function fixVariableNameRange(Variable $node, Range $range, string $fileContents): Range
    {
        if (!($node->name instanceof Token)) {
            return $range;
        }
        $variableName = (string)$node->name->getText($fileContents);
        
        if (mb_substr($variableName, 0, 1) == '$') {
            $range->start->character++;
        } // compensate for the dollar
        return $range;
    }

    private function getTokenRange(Token $token, string $document): Range
    {
        return new Range(
            PositionConverter::intByteOffsetToPosition($token->getStartPosition(), $document),
            PositionConverter::intByteOffsetToPosition($token->getEndPosition(), $document)
        );
    }

    private function loadText(string $uri): string
    {
        if ($this->workspace->has($uri)) {
            return $this->workspace->get($uri)->text;
        }

        $contents = @file_get_contents($uri);

        if (false === $contents) {
            throw new CouldNotLoadFileContents(sprintf(
                'Could not load file contents "%s"',
                $uri
            ));
        }

        return $contents;
    }
}
