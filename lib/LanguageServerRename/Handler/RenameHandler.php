<?php

namespace Phpactor\Extension\LanguageServerRename\Handler;

use Amp\Promise;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Parser;
use Phpactor\Extension\LanguageServerBridge\Converter\PositionConverter;
use Phpactor\LanguageServerProtocol\PrepareRenameParams;
use Phpactor\LanguageServerProtocol\PrepareRenameRequest;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\RenameOptions;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;

class RenameHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;
    /**
     * @var Parser
     */
    private $parser;


    public function __construct(Workspace $workspace, Parser $parser)
    {
        $this->workspace = $workspace;
        $this->parser = $parser;
    }
    /**
     * {@inheritDoc}
     */
    public function methods(): array
    {
        return [
            PrepareRenameRequest::METHOD => 'prepareRename',
        ];
    }
    
    public function prepareRename(PrepareRenameParams $params): Promise
    {
        // https://microsoft.github.io/language-server-protocol/specification#textDocument_prepareRename
        return \Amp\call(function () use ($params) {
            $textDocument = $this->workspace->get($params->textDocument->uri);
            $offset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);
            
            $rootNode = $this->parser->parseSourceFile($textDocument->text);
            $node = $rootNode->getDescendantNodeAtPosition($offset->toInt());
            
            $ok = false;
            if ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class)) {
                /** @var Variable $node  */
                dump("Property: {$node->getName()}");
                $ok = true;
            } elseif ($node instanceof Parameter) {
                /** @var Parameter $node */
                dump("Parameter: {$node->getName()}");
                $ok = true;
            } elseif ($node instanceof Variable) {
                /** @var Variable $node */
                dump("Variable: {$node->getName()}");
                $ok = true;
            } elseif ($node instanceof MethodDeclaration) {
                /** @var MethodDeclaration $node */
                dump("Method: {$node->getName()}");
                $ok = true;
            } elseif ($node instanceof ClassDeclaration) {
                /** @var ClassDeclaration $node */
                dump("Class: {$node->getNamespacedName()}");
                $ok = true;
            } elseif ($node instanceof ConstElement) {
                /** @var ConstElement $node */
                dump("Class: {$node->getNamespacedName()}");
                $ok = true;
            } else {
                dump(get_class($node));
            }

            $position = $params->position;
            return $ok ? new Range($params->position, $params->position) : null;
            // return [ "defaultBehavior" => false ];
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->renameProvider = new RenameOptions(true);
    }
}
