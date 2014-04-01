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

include "standard.php";
include "database.php";
include "input.php";

/**
 *  The entry point for new location samples to be inserted into the database. Location samples come up in JSON as
 *  formatted by the getJSON method in us.dustinj.locationstore.io.LocationExporter.java using the database fields
 *  defined in the method GetColumnDefinitions in us.dustinj.locationstore.LocationDatabase.java.
 */

$mysqli = connectToDB();

if ($debug)
{
    echo json_encode($inputJson, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT) . "\n\n";
}

$usersResult = $mysqli->query("SELECT * FROM users WHERE apiKey = \"".$mysqli->real_escape_string($inputApiKey)."\"") or die($mysqli->error.__LINE__);
if ($usersResult->num_rows > 0)
{
    $userRow = $usersResult->fetch_assoc();
    $internalID = $userRow['internalID'];

    foreach($inputJson as $Location)
    {
        $declerations = "`internalID`, ";
        $values = "\"".$internalID."\", ";

        foreach($Location as $column => $value)
        {
            if ($column != null && $value != null)
            {
                $declerations .= "`".$column . "`, ";
                $values .= "\"".$mysqli->real_escape_string($value)."\", ";
            }
        }

        $declerations = rtrim($declerations, ", ");
        $values = rtrim($values, ", ");

        $query = "INSERT INTO locations (" . $declerations . ") VALUES (" . $values . ");";
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
    }
}
else
{
    http_response_code(401); // Unauthorized
}

mysqli_close($mysqli);

?>