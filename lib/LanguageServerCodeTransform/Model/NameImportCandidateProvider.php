<?php

namespace Phpactor\Extension\LanguageServerCodeTransform\Model;

use Generator;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Parser;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\Indexer\Model\SearchClient;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\ByteOffset;

class NameImportCandidateProvider
{
    /**
     * @var SearchClient
     */
    private $client;

    /**
     * @var Parser
     */
    private $parser;

    public function __construct(SearchClient $client, ?Parser $parser = null)
    {
        $this->client = $client;
        $this->parser = $parser ?: new Parser();
    }

    /**
     * @return Generator<FullyQualifiedName>
     */
    public function provide(string $source, ByteOffset $byteOffset): Generator
    {
        $root = $this->parser->parseSourceFile($source);
        $node = $root->getDescendantNodeAtPosition($byteOffset->toInt());

        if (null === $name = $this->resolveShortName($node)) {
            return;
        }

        foreach ($this->client->search($name) as $record) {
            if (!$record instanceof ClassRecord) {
                continue;
            }

            yield $record->fqn();
        }
    }

    private function resolveShortName(Node $node): ?string
    {
        if ($node instanceof QualifiedName) {
            return $this->fromQualifiedName($node);
        }

        return null;
    }

    private function fromQualifiedName(QualifiedName $node): string
    {
        return $node->getText();
    }
}
