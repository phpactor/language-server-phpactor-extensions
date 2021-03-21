<?php

namespace Phpactor\Extension\LanguageServerRename\Tests;

use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\ByteOffsetRange;
use RuntimeException;

class OffsetExtractor
{
    /**
     * @var array
     */
    private $points = [];
    /**
     * @var array
     */
    private $rangeOpenMarkers = [];
    /**
     * @var array
     */
    private $rangeCloseMarkers = [];
    /**
     * @var array<string,ByteOffset[]>
     */
    private $pointResults = [];
    /**
     * @var array<string,ByteOffsetRange[]>
     */
    private $rangeResults = [];
    /**
     * @var string
     */
    private $source;


    private function __construct()
    {
    }

    public static function create(): OffsetExtractor
    {
        return new OffsetExtractor();
    }

    public function registerPoint(string $name, string $marker): OffsetExtractor
    {
        $this->points[$marker] = $name;
        return $this;
    }

    public function registerRange(string $name, string $openMarker, string $closeMarker): OffsetExtractor
    {
        $this->rangeOpenMarkers[$openMarker] = $name;
        $this->rangeCloseMarkers[$closeMarker] = $name;
        return $this;
    }

    public function parse(string $source): OffsetExtractor
    {
        $markers = array_merge(
            array_keys($this->points),
            array_keys($this->rangeOpenMarkers),
            array_keys($this->rangeCloseMarkers),
        );
        $results = preg_split('/('. implode('|', $markers) .')/u', $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        if (!is_array($results)) {
            return $this;
        }

        $newSource = '';
        $this->pointResults = [];
        $this->rangeResults = [];
        $offset = 0;
        $currentRangeStartOffset = 0;

        foreach ($this->points as $marker=>$name) {
            $this->pointResults[$name] = [];
        }
        foreach ($this->rangeCloseMarkers as $marker=>$name) {
            $this->rangeResults[$name] = [];
        }

        foreach ($results as $result) {
            if (isset($this->points[$result])) {
                $this->pointResults[$this->points[$result]][] = ByteOffset::fromInt($offset);
                continue;
            }
            
            if (isset($this->rangeOpenMarkers[$result])) {
                $currentRangeStartOffset = $offset;
                continue;
            }

            if (isset($this->rangeCloseMarkers[$result])) {
                $this->rangeResults[$this->rangeCloseMarkers[$result]][] = ByteOffsetRange::fromInts($currentRangeStartOffset, $offset);
                continue;
            }
            
            $offset += strlen($result);
            $newSource .= $result;
        }
        
        $this->source = $newSource;

        return $this;
    }
    /**
     * @return ByteOffset[]
     */
    public function points(string $name): array
    {
        if (!isset($this->pointResults[$name])) {
            throw new RuntimeException('No registered point by that name.');
        }

        return $this->pointResults[$name];
    }
    /**
     * @return ByteOffsetRange[]
     */
    public function ranges(string $name): array
    {
        if (!isset($this->rangeResults[$name])) {
            throw new RuntimeException('No registered range by that name.');
        }

        return $this->rangeResults[$name];
    }

    public function source(): string
    {
        return $this->source;
    }
}
