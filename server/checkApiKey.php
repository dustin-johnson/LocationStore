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

http_response_code(500); // Default to 'Internal Server Error'

$mysqli = connectToDB();

if ($isInputApiKeySet)
{
    $result = $mysqli->query("SELECT * FROM users WHERE apiKey = \"".$mysqli->real_escape_string($inputApiKey)."\"") or die($mysqli->error.__LINE__);
    if (!$result)
    {
        if (!$debug)
        {
            http_response_code(500); // Internal Server Error, this prevents any data response back
                                     // to the client, so that text description below gets lost.
        }

        die($mysqli->error.__LINE__);
    }

    if($result->num_rows > 0)
    {
        http_response_code(200); // OK
    }
    else
    {
        http_response_code(401); // Unauthorized, user not found
    }

    mysqli_free_result($result);
}
else
{
    http_response_code(401); // Unauthorized, user not found
}

mysqli_close($mysqli);

?>