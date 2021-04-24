<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\ClassMover;

use Amp\Promise;
use Phpactor\ClassFileConverter\ClassToFileConverter;
use Phpactor\ClassFileConverter\Domain\ClassName;
use Phpactor\ClassFileConverter\Domain\ClassToFile;
use Phpactor\ClassFileConverter\Domain\FilePath;
use Phpactor\ClassFileConverter\Domain\FileToClass;
use Phpactor\ClassMover\ClassMover;
use Phpactor\Extension\LanguageServerRename\Model\Exception\CouldNotRename;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer as PhpactorFileRenamer;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdit;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEdits;
use Phpactor\Extension\LanguageServerRename\Model\LocatedTextEditsMap;
use Phpactor\Indexer\IndexAgent;
use Phpactor\Indexer\Model\QueryClient;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdits;
use function Amp\call;

class FileRenamer implements PhpactorFileRenamer
{
    /**
     * @var FileToClass
     */
    private $fileToClass;

    /**
     * @var QueryClient
     */
    private $client;

    /**
     * @var ClassMover
     */
    private $mover;

    /**
     * @var TextDocumentLocator
     */
    private $locator;

    public function __construct(
        FileToClass $fileToClass,
        TextDocumentLocator $locator,
        QueryClient $client,
        ClassMover $mover
    )
    {
        $this->fileToClass = $fileToClass;
        $this->client = $client;
        $this->mover = $mover;
        $this->locator = $locator;
    }

    public function renameFile(TextDocumentUri $from, TextDocumentUri $to): Promise
    {
        return call(function () use ($from, $to) {
            $fromClass = $this->fileToClass->fileToClassCandidates(
                FilePath::fromString($from->path())
            )->best();

            $toClass = $this->fileToClass->fileToClassCandidates(
                FilePath::fromString($to->path())
            )->best();

            $references = $this->client->class()->referencesTo($fromClass->__toString());

            // rename class definition
            $locatedEdits = $this->replaceDefinition($to, $fromClass, $toClass);

            $edits = TextEdits::none();
            $seen = [];
            foreach ($references as $reference) {
                if (isset($seen[$reference->location()->uri()->path()])) {
                    continue;
                }

                $seen[$reference->location()->uri()->path()] = true;

                $document = $this->locator->get($reference->location()->uri());

                foreach ($this->mover->replaceReferences(
                    $this->mover->findReferences($document->__toString(), $fromClass),
                    $toClass
                ) as $edit) {
                $locatedEdits[] = new LocatedTextEdit($reference->location()->uri(), $edit);
                }
            }

            return LocatedTextEditsMap::fromLocatedEdits($locatedEdits);
        });
    }

    private function replaceDefinition(TextDocumentUri $file, ClassName $fromClass, ClassName $toClass): array
    {
        $document = $this->locator->get($file);
        $locatedEdits = [];
        foreach ($this->mover->replaceReferences(
            $this->mover->findReferences($document, $fromClass),
            $toClass
        ) as $edit) {
            $locatedEdits[] = new LocatedTextEdit($file, $edit);
        }

        return $locatedEdits;
    }
}
