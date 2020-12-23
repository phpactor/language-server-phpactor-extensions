<?php

namespace Phpactor\Extension\LanguageServerWorseReflection\Workspace;

use Phpactor\TextDocument\Exception\TextDocumentNotFound;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\WorseReflection\Core\Name;
use Phpactor\WorseReflection\Core\Reflector\SourceCodeReflector;
use RuntimeException;

class WorkspaceIndex
{
    /**
     * @var SourceCodeReflector
     */
    private $reflector;

    /**
     * @var array<string, TextDocument>
     */
    private $byName = [];
    /**
     * @var array<string, TextDocument>
     */
    private $documents = [];
    /**
     * @var array<string, array<string>>
     */
    private $documentToNameMap = [];

    public function __construct(SourceCodeReflector $reflector)
    {
        $this->reflector = $reflector;
    }

    public function documentForName(Name $name): ?TextDocument
    {
        if (isset($this->byName[$name->full()])) {
            return $this->byName[$name->full()];
        }

        return null;
    }

    public function index(TextDocument $textDocument): void
    {
        $this->documents[(string)$textDocument->uri()] = $textDocument;
        $this->updateDocument($textDocument);
    }

    private function updateDocument(TextDocument $textDocument): void
    {
        $newNames = [];
        foreach ($this->reflector->reflectClassesIn($textDocument) as $reflectionClass) {
            $newNames[] = $reflectionClass->name()->full();
        }
        
        foreach ($this->reflector->reflectFunctionsIn($textDocument) as $reflectionFunction) {
            $newNames[] = $reflectionFunction->name()->full();
        }

        $this->updateNames($textDocument, $newNames, $this->documentToNameMap[(string)$textDocument->uri()] ?? []);
    }

    private function updateNames(TextDocument $textDocument, array $newNames, array $currentNames): void
    {
        $namesToRemove = array_diff($currentNames, $newNames);
        
        foreach($newNames as $name) {
            $this->byName[$name] = $textDocument;
        }
        foreach($namesToRemove as $name){
            unset($this->byName[$name]);
        }

        if(!empty($newNames))
            $this->documentToNameMap[(string)$textDocument->uri()] = $newNames;
        else
            unset($this->documentToNameMap[(string)$textDocument->uri()]);
    }

    public function update(TextDocumentUri $textDocumentUri, string $updatedText): void
    {
        $textDocument = $this->documents[(string)$textDocumentUri] ?? null;
        if($textDocument === null){
            throw new RuntimeException(sprintf(
                'Could not find document "%s"',
                $textDocumentUri->__toString()
            ));
        }
        $this->updateDocument(TextDocumentBuilder::fromTextDocument($textDocument)->text($updatedText)->build());
    }

    public function remove(TextDocumentUri $textDocumentUri): void
    {
        $textDocument = $this->documents[(string)$textDocumentUri] ?? null;
        if($textDocument === null){
            throw new RuntimeException(sprintf(
                'Could not find document "%s"',
                $textDocumentUri->__toString()
            ));
        }
        $this->updateNames($textDocument, [], $this->documentToNameMap[(string)$textDocument->uri()] ?? []);
        unset($this->documents[(string)$textDocumentUri]);
    }
}
