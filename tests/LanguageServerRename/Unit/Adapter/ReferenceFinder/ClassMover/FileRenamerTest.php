<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Unit\Adapter\ReferenceFinder\ClassMover;

use PHPUnit\Framework\TestCase;
use Phpactor\ClassFileConverter\Adapter\Simple\SimpleFileToClass;
use Phpactor\ClassMover\ClassMover;
use Phpactor\Extension\LanguageServerRename\Adapter\ReferenceFinder\ClassMover\FileRenamer;
use Phpactor\Extension\LanguageServerRename\Tests\IntegrationTestCase;
use Phpactor\Indexer\Adapter\Php\InMemory\InMemoryIndex;
use Phpactor\Indexer\Model\QueryClient;
use Phpactor\Indexer\Model\RecordReference;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\Indexer\Model\Record\FileRecord;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentLocator\InMemoryDocumentLocator;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\TextDocument\TextEdits;
use function Amp\Promise\wait;

class FileRenamerTest extends IntegrationTestCase
{
    public function testRename(): void
    {
        $document1 = TextDocumentBuilder::create('<?php class One{}')->uri($this->path('1.php'))->build();
        $document2 = TextDocumentBuilder::create('<?php class Two{}')->uri($this->path('2.php'))->build();
        $document3 = TextDocumentBuilder::create('<?php One::class;')->uri($this->path('3.php'))->build();

        $renamer = $this->createRenamer([$document1, $document2, $document3], [
            (new ClassRecord('One'))->setType('class')->addReference($this->path('3.php')),
            (new FileRecord($this->path('3.php')))->addReference(new RecordReference(ClassRecord::RECORD_TYPE, 'One', 10))
        ]);

        $edits = wait($renamer->renameFile($document1->uri(), $document2->uri()));
        self::assertInstanceOf(TextEdits::class, $edits);
        self::assertCount(1, $edits);
    }

    private function createRenamer(array $textDocuments, array $records): FileRenamer
    {
        foreach ($textDocuments as $textDocument) {
            assert($textDocument instanceof TextDocument);
            /** @phpstan-ignore-next-line */
            file_put_contents($textDocument->uri()->path(), $textDocument->__toString());
        }

        return new FileRenamer(
            new SimpleFileToClass(),
            InMemoryDocumentLocator::fromTextDocuments($textDocuments),
            new QueryClient(new InMemoryIndex($records)),
            new ClassMover(),
        );
    }

    private function path(string $path): string
    {
        return $this->workspace()->path($path);
    }
}
