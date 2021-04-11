<?php

namespace Phpactor\Extension\LanguageServerRename\Tests\Util;

use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use RuntimeException;

class OffsetExtractorResult
{
    /**
     * @var array<string,ByteOffset[]>
     */
    private $points = [];

    /**
     * @var array<string,ByteOffsetRange[]>
     */
    private $ranges = [];

    /**
     * @var string
     */
    private $source;

    public function __construct(string $source, array $pointResults, array $rangeResults)
    {
        $this->source = $source;
        $this->points = $pointResults;
        $this->ranges = $rangeResults;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return ByteOffset[]
     */
    public function points(string $name): array
    {
        if (!isset($this->points[$name])) {
            throw new RuntimeException(sprintf(
                'No point registered with name "%s", known names "%s"',
                $name,
                implode('", "', array_keys($this->points))
            ));
        }

        return $this->points[$name];
    }

    public function point(string $name): ByteOffset
    {
        $points = $this->points($name);

        if (!count($points)) {
            throw new RuntimeException(sprintf(
                'No "%s" points found in source code',
                $name
            ));
        }

        $point = reset($points);

        return $point;
    }

    /**
     * @return ByteOffsetRange[]
     */
    public function ranges(string $name): array
    {
        if (!isset($this->ranges[$name])) {
            throw new RuntimeException(sprintf(
                'No range registered with name "%s", known names "%s"',
                $name,
                implode('", "', array_keys($this->ranges))
            ));
        }

        return $this->ranges[$name];
    }

    public function range(string $name): ByteOffsetRange
    {
        $ranges = $this->ranges($name);

        if (!count($ranges)) {
            throw new RuntimeException(sprintf(
                'No "%s" ranges found in source code',
                $name
            ));
        }

        $range = reset($ranges);

        return $range;
    }
}
