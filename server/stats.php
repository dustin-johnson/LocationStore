<!--
    LocationStore - Copyright (C) 2013  Dustin Johnson

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
-->

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" name="viewport" content="initial-scale=1.0, user-scalable=no" />
        <link rel="shortcut icon" href="favicon.ico" />
        <title>Location Stats</title>
    </head>
    <body>
<?php
include "standard.php";
include "database.php";
include "input.php";

class TimeQueryResult
{
    public $Count;
    public $StartTimestampSec;
    public $EndTimestampSec;
}

$timezone = "America/Los_Angeles";
$mysqli = connectToDB();

$executionStartTime = microtime(true);
$totalTimeResult = timeQuery($mysqli, 0, PHP_INT_MAX);
$startOfDay = $totalTimeResult->StartTimestampSec;
$dateTimeFormatter = new DateTime(null, new DateTimeZone($timezone));

$totalLocationCount = 0;
while ($startOfDay <= $totalTimeResult->EndTimestampSec) {
    $endOfDay = getEndOfDayTimestamp($startOfDay, $timezone);

    $currentTimeResult = timeQuery($mysqli, $startOfDay * 1000, $endOfDay * 1000 - 1);
    $totalLocationCount += $currentTimeResult->Count;
    $dateTimeFormatter->setTimestamp($startOfDay);
    $currentDayStr = $dateTimeFormatter->format('m/d/Y');
    echo "<a href=\"map.php?count=".$currentTimeResult->Count."&minAccuracy_m=".$inputMinAccuracy_m."&minLocationTimestamp=".($startOfDay*1000)."&maxLocationTimestamp=".($endOfDay*1000-1)."&exportID=".$inputExportID."\">".$currentDayStr."</a> - ".$currentTimeResult->Count."<br>";
    $startOfDay = $endOfDay;
}

if ($totalLocationCount != $totalTimeResult->Count)
{
    echo "Error in the php script!";
}

$everythingQuery = "count=".$totalTimeResult->Count."&minAccuracy_m=".$inputMinAccuracy_m."&exportID=".$inputExportID;
echo "<br><a href=\"map.php?".$everythingQuery."\">Total count</a> (<a href=\"getLocationData.php?".$everythingQuery."&outputFormat=gpx\">GPX</a> <a href=\"getLocationData.php?".$everythingQuery."&outputFormat=kml\">KML</a>): ".number_format($totalTimeResult->Count, 0, ".", ",");

mysqli_close($mysqli);

echo "<br><br>";
echo "Execution time: ".number_format(round(microtime(true)-$executionStartTime, 3, PHP_ROUND_HALF_UP), 3)." seconds";

// Returns the unix timestamp for the end of the day given in the input and timezone
function getEndOfDayTimestamp($inputTimestamp, $timezoneStr)
{
    $date = new DateTime(null, new DateTimeZone($timezoneStr));
    $date->setTimestamp($inputTimestamp);
    $offsetSeconds = $date->getOffset();
    $currentTimeLocal = $inputTimestamp + $offsetSeconds;
    $startOfCurrentDayLocal = $currentTimeLocal - ($currentTimeLocal % (3600 * 24));
    $endOfCurrentDayLocal = $startOfCurrentDayLocal + 3600 * 24;

    // echo "inputTimestamp: $inputTimestamp,   offsetSeconds: $offsetSeconds,   currentTimeLocal: $currentTimeLocal,   startOfCurrentDayLocal: $startOfCurrentDayLocal,   endOfCurrentDayLocal: $endOfCurrentDayLocal<br>";
    return $endOfCurrentDayLocal - $offsetSeconds;
}

// Returns a TimeQueryResult
function timeQuery($mysqli, $startTime, $endTime)
{
    global $inputExportID, $inputMinLocationTimestamp, $inputMaxLocationTimestamp, $inputMinLat_deg, $inputMaxLat_deg, $inputMinLon_deg, $inputMaxLon_deg, $inputMinAccuracy_m;

    $exportWhereClause = "exports.exportID = \"" . $mysqli->real_escape_string($inputExportID) . "\" and " .
                         "exports.internalID = locations.internalID and " .
                         "locations.locationTimestamp >= exports.startTimestamp and " .
                         "locations.locationTimestamp <= (exports.startTimestamp + exports.durationMs)";

    $inputWhereClause = "locations.locationTimestamp >= \"" . max($inputMinLocationTimestamp, $startTime) . "\" and " .
                        "locations.locationTimestamp <= \"" . min($inputMaxLocationTimestamp, $endTime) . "\" and " .
                        "locations.lat_deg >= \"" . $inputMinLat_deg . "\" and " .
                        "locations.lat_deg <= \"" . $inputMaxLat_deg . "\" and " .
                        "locations.lon_deg >= \"" . $inputMinLon_deg . "\" and " .
                        "locations.lon_deg <= \"" . $inputMaxLon_deg . "\" and " .
                        "locations.accuracy_m <= \"" . $inputMinAccuracy_m . "\"";

    $selectClause = "COUNT(*) as count, " .
                    "MIN(locations.locationTimestamp) as startTimestamp, " .
                    "MAX(locations.locationTimestamp) as endTimestamp";

    $queryStartTime = microtime(true);
    $locationResult = $mysqli->query("SELECT ".$selectClause." FROM exports, locations WHERE ".$exportWhereClause." and ".$inputWhereClause) or die($mysqli->error.__LINE__);
    $queryEndTime = microtime(true);

    if($locationResult->num_rows > 0)
    {
        $resultRow = $locationResult->fetch_assoc();
        $queryResult = new TimeQueryResult();

        $queryResult->Count             = $resultRow['count'];
        $queryResult->StartTimestampSec = $resultRow['startTimestamp'] / 1000;
        $queryResult->EndTimestampSec   = $resultRow['endTimestamp'] / 1000;

        mysqli_free_result($locationResult);
        return $queryResult;
    }
    else
    {
        echo "SQL query failed to return any values";
        die();
    }
}
?>
    </body>
</html>