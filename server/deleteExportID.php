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
    $query = "UPDATE exports SET active = FALSE WHERE modifiable = TRUE and " . 
                                       "internalID = \"".$mysqli->real_escape_string($internalID)."\" and " .
                                       "exportID = \"".$mysqli->real_escape_string($inputExportID)."\";";
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

    if ($mysqli->affected_rows == 0)
    {
        http_response_code(304); // Not Modified.  There was no such export to delete.
    }
}

mysqli_close($mysqli);
?>