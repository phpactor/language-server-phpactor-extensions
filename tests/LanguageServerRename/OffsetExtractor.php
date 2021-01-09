<?php

namespace Phpactor\Extension\LanguageServerRename\Tests;

use function array_keys;

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

        foreach ($results as $result) {
            if (isset($this->points[$result])) {
                $retVal[$this->points[$result]] = $this->pointCreateors[$result]($offset, $newSource);
                continue;
            }
            
            if (isset($this->rangeOpenMarkers[$result])) {
                $currentRangeStartOffset = $offset;
                continue;
            }

            if (isset($this->rangeCloseMarkers[$result])) {
                $retVal[$this->rangeCloseMarkers[$result]] = $this->rangeCreateors[$result]($currentRangeStartOffset, $offset, $newSource);
                continue;
            }
            

            $offset += strlen($result);
            $newSource .= $result;
        }

        $retVal['newSource'] = $newSource;

        return $retVal;
    }

    // public function offsetsFromSource(string $source, ?string $uri): array
    // {
    //     $textDocumentUri = $uri !== null ? TextDocumentUri::fromString($uri) : null;
    //     $results = preg_split("/(<>|<d>|<r>|{{|«|}}|»)/u", $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
    //     $referenceLocations = [];
    //     $definitionLocation = null;
    //     $selectionOffset = null;
    //     $ranges = [];
    //     $currentResultStartOffset = null;
    //     if (is_array($results)) {
    //         $newSource = "";
    //         $offset = 0;
    //         foreach ($results as $result) {
    //             if ($result == "<>") {
    //                 $selectionOffset = $offset;
    //             } elseif ($result == "<d>") {
    //                 $definitionLocation = new DefinitionLocation($textDocumentUri, ByteOffset::fromInt($offset));
    //             } elseif ($result == "<r>") {
    //                 $referenceLocations[] = PotentialLocation::surely(
    //                     new Location($textDocumentUri, ByteOffset::fromInt($offset))
    //                 );
    //             } elseif ($result == "{{" || $result == "«") {
    //                 $currentResultStartOffset = $offset;
    //             } elseif ($result == "}}" || $result == "»") {
    //                 $ranges[] =
    //                     new Range(
    //                         PositionConverter::byteOffsetToPosition(ByteOffset::fromInt($currentResultStartOffset), $source),
    //                         PositionConverter::byteOffsetToPosition(ByteOffset::fromInt($offset), $source)
    //                     );
    //             } else {
    //                 $newSource .= $result;
    //                 $offset += mb_strlen($result);
    //             }
    //         }
    //     } else {
    //         throw new \Exception('No selection.');
    //     }
        
    //     return [$newSource, $selectionOffset, $definitionLocation, $referenceLocations, $ranges];
    // }
}
