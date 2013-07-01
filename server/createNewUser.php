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

include "database.php";
include "input.php";

$mysqli = connectToDB();
$UserData = array();

if (strlen($inputUserID) > 0)
{
    $apiKey = md5(uniqid(mt_rand(), true));
    $internalID = uniqid("", true);
    $query = "INSERT INTO users (userID, internalID, apiKey) VALUES (\"".$mysqli->real_escape_string($inputUserID)."\", ".
                                                                    "\"".$mysqli->real_escape_string($internalID)."\", ".
                                                                    "\"".$mysqli->real_escape_string($apiKey)."\");";
    if ($debug)
    {
        echo $query . "\n\n";
    }

    $result = $mysqli->query($query);
    if (!$result)
    {
        if (!$debug)
        {
            http_response_code(500); // Internal Server Error, this prevents any data response back
                                     // to the client, so that text description below gets lost.
        }

        die($mysqli->error.__LINE__);
    }

    $UserData['apiKey'] = $mysqli->real_escape_string($apiKey);
}
else
{
    http_response_code(500); // Internal Server Error, the userID must have been provided
}

mysqli_close($mysqli);

header("Content-type: application/json"); 
echo json_encode($UserData, JSON_NUMERIC_CHECK | ($debug ? JSON_PRETTY_PRINT : 0));

?>