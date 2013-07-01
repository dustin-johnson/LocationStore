<?php
/**
 *  LocationStore - Copyright (C) 2013  Dustin Johnson
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

ini_set("display_errors", "1");
ERROR_REPORTING(E_ALL);

include "standard.php";
include "database.php";
include "input.php";

$mysqli = connectToDB();
$ExportData = array();

$usersResult = $mysqli->query("SELECT * FROM users WHERE apiKey = \"".$mysqli->real_escape_string($inputApiKey)."\"") or die($mysqli->error.__LINE__);
if ($usersResult->num_rows > 0)
{
    $userRow = $usersResult->fetch_assoc();
    $internalID = $userRow['internalID'];
    $exportID = md5(uniqid(mt_rand(), true));
    $prettyStartTime = findPreviousPrettyTime($inputStartTimestamp);      // Floor the start time to an even multiple of 5 minutes
    $prettyDurationMs = 0;

    // Check if we've been instructed to never allow the export to expire
    if ($inputDurationMs < 0)
    {
        $prettyDurationMs = PHP_INT_MAX - $prettyStartTime;
    }
    else
    {
        $prettyDurationMs = $inputDurationMs;
        $prettyDurationMs += round(($inputStartTimestamp - $prettyStartTime) / 1000); // Correct the duration in accordance with the correction made to the start time
    }

    $query = "INSERT INTO exports (exportID, internalID, startTimestamp, durationMs) VALUES (\"".$mysqli->real_escape_string($exportID)."\", ".
                                                                                            "\"".$mysqli->real_escape_string($internalID)."\", ".
                                                                                            "\"".$mysqli->real_escape_string($prettyStartTime)."\", ".
                                                                                            "\"".$mysqli->real_escape_string($prettyDurationMs)."\");";
    if ($debug)
    {
        echo $query . "\n\n";
    }

    $result = $mysqli->query($query);
    if (!$result && $mysqli->errno != 1062) // 1062 == "Duplicate entry..."
    {
        if (!$debug)
        {
            http_response_code(500); // Internal Server Error, this prevents any data response back
                                     // to the client, so that text description below gets lost.
        }

        die($mysqli->error.__LINE__);
    }

    $ExportData['exportID'] = $mysqli->real_escape_string($exportID);
    $ExportData['startTimestamp'] = $mysqli->real_escape_string($prettyStartTime);
    $ExportData['durationMs'] = $mysqli->real_escape_string($prettyDurationMs);
}

mysqli_close($mysqli);

header("Content-type: application/json"); 
echo json_encode($ExportData, JSON_NUMERIC_CHECK | ($debug ? JSON_PRETTY_PRINT : 0));

// Round time to the previous 5 minute mark
function findPreviousPrettyTime($timeMs)
{
    return $timeMs - ($timeMs % (5 * 60 * 1000));
}

?>