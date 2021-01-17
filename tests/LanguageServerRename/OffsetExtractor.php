<?php

namespace Phpactor\Extension\LanguageServerRename\Tests;

class OffsetExtractor
{
    /** @var array */
    private $points = [];
    /** @var \Closure[] */
    private $pointCreateors = [];

    /** @var array */
    private $rangeOpenMarkers = [];
    /** @var array */
    private $rangeCloseMarkers = [];
    /** @var \Closure[] */
    private $rangeCreateors = [];

    public function registerPoint(string $name, string $marker, \Closure $creator = null): void
    {
        $this->points[$marker] = $name;
        $this->pointCreateors[$marker] = $creator ?? function (int $offset) {
            return $offset;
        };
    }

    public function registerRange(string $name, string $openMarker, string $closeMarker, \Closure $creator = null): void
    {
        $this->rangeOpenMarkers[$openMarker] = $name;
        $this->rangeCloseMarkers[$closeMarker] = $name;
        $this->rangeCreateors[$closeMarker] = $creator ?? function (int $startOffset, int $endOffset) {
            return ['start'=>$startOffset, 'end'=>$endOffset];
        };
    }

    public function parse(string $source): array
    {
        $markers = array_merge(
            array_keys($this->points),
            array_keys($this->rangeOpenMarkers),
            array_keys($this->rangeCloseMarkers),
        );
        $results = preg_split("/(". implode("|", $markers) .")/u", $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        if (!is_array($results)) {
            return [];
        }

        $newSource = "";
        $retVal = [];
        $offset = 0;
        $currentRangeStartOffset = 0;

        foreach($this->points as $marker=>$name){
            $retVal[$name] = [];
        }
        foreach($this->rangeCloseMarkers as $marker=>$name){
            $retVal[$name] = [];
        }

        foreach ($results as $result) {
            if (isset($this->points[$result])) {
                if (!isset($retVal[$this->points[$result]])) {
                    $retVal[$this->points[$result]] = [];
                }
                $retVal[$this->points[$result]][] = $this->pointCreateors[$result]($offset, $newSource);
                continue;
            }
            
            if (isset($this->rangeOpenMarkers[$result])) {
                $currentRangeStartOffset = $offset;
                continue;
            }

            if (isset($this->rangeCloseMarkers[$result])) {
                if (!isset($retVal[$this->rangeCloseMarkers[$result]])) {
                    $retVal[$this->rangeCloseMarkers[$result]] = [];
                }
                $retVal[$this->rangeCloseMarkers[$result]][] = $this->rangeCreateors[$result]($currentRangeStartOffset, $offset, $newSource);
                continue;
            }
            

            $offset += strlen($result);
            $newSource .= $result;
        }

        $retVal['newSource'] = $newSource;

        return $retVal;
    }
}
