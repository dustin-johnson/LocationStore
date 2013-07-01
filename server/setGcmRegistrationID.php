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

if ($isInputApiKeySet && $isInputGcmRegistrationIDSet)
{
    $usersResult = $mysqli->query("SELECT * FROM users WHERE apiKey = \"".$mysqli->real_escape_string($inputApiKey)."\"") or die($mysqli->error.__LINE__);
    if ($usersResult->num_rows > 0)
    {
        $userRow = $usersResult->fetch_assoc();

        http_response_code(200); // Change default to 'Success'
        if ($userRow['gcmRegistrationID'] != $mysqli->real_escape_string($inputGcmRegistrationID))
        {
            $result = $mysqli->query("UPDATE users SET gcmRegistrationID = \"".$mysqli->real_escape_string($inputGcmRegistrationID)."\" ".
                                                  "WHERE apiKey = \"".$mysqli->real_escape_string($inputApiKey)."\"") or die($mysqli->error.__LINE__);

            if ($mysqli->affected_rows == 0)
            {
                http_response_code(304); // Not Modified.  No such user found or other error.
            }
        }
    }
    else
    {
        http_response_code(401); // Unauthorized, user not found
    }
}
else
{
    http_response_code(401); // Unauthorized, not enough information passed in
}

mysqli_close($mysqli);

?>