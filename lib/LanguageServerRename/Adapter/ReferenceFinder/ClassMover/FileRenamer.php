<?php

namespace Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\ClassMover;

use Amp\Promise;
use Phpactor\ClassFileConverter\ClassToFileConverter;
use Phpactor\ClassFileConverter\Domain\ClassToFile;
use Phpactor\ClassFileConverter\Domain\FilePath;
use Phpactor\ClassFileConverter\Domain\FileToClass;
use Phpactor\ClassMover\ClassMover;
use Phpactor\Extension\LanguageServerRename\Model\Exception\CouldNotRename;
use Phpactor\Extension\LanguageServerRename\Model\FileRenamer as PhpactorFileRenamer;
use Phpactor\Indexer\IndexAgent;
use Phpactor\Indexer\Model\QueryClient;
use Phpactor\TextDocument\TextDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;

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
        $fromClass = $this->fileToClass->fileToClassCandidates(
            FilePath::fromString($from->path())
        )->best();

        $toClass = $this->fileToClass->fileToClassCandidates(
            FilePath::fromString($to->path())
        )->best();

        $references = $this->client->class()->referencesTo($fromClass->__toString());

        foreach ($references as $reference) {
            $document = $this->locator->get($reference->location()->uri());

            $this->mover->replaceReferences(
                $this->mover->findReferences($document->__toString(), $fromClass),
                $toClass
            );
        }
    }
}
