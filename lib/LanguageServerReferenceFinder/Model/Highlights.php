<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Model;

use ArrayIterator;
use Countable;
use Generator;
use Iterator;
use IteratorAggregate;
use Phpactor\LanguageServerProtocol\DocumentHighlight;
use RuntimeException;
use phpDocumentor\Reflection\Types\Iterable_;





/**
 * @implements IteratorAggregate<DocumentHighlight>
 */
class Highlights implements IteratorAggregate, Countable
{
    /**
     * @var array<DocumentHighlight>
     */
    private $highlights;

    public function __construct(DocumentHighlight ...$highlights)
    {
        $this->highlights = $highlights;
    }
    
    public function first(): DocumentHighlight
    {
        if (empty($this->highlights)) {
            throw new RuntimeException(
                'Document highlights are empty'
            );
        }

        return $this->highlights[0];
    }

    /**
     * @return ArrayIterator<int,DocumentHighlight>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->highlights);
    }

    public static function fromIterator(Iterator $iterator): self
    {
        return new self(...iterator_to_array($iterator));
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->highlights);
    }

    public static function empty(): self
    {
        return new self();
    }
}
