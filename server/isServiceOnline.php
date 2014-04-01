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
 *  This function is used to determine if the web application is alive and well, including its access to the database.
 *  It's original (and currently only) purpose is for uptime services to query on a periodic basis to alert the
 *  administrator when the application is no longer accessible or in an erroneous state. http://siteuptime.com/ is
 *  currently being used for the mainline's service state monitoring.
 */

http_response_code(500); // Default to 'Internal Server Error'
$isOnline = false;
$mysqli = connectToDB();

$queryResult = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE 1");

if($queryResult->num_rows > 0)
{
    $resultRow = $queryResult->fetch_assoc();

    if ($resultRow['count'] > 0)
    {
        http_response_code(200); // Success
        $isOnline = true;
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" name="viewport" content="initial-scale=1.0, user-scalable=no" />
        <link rel="shortcut icon" href="favicon.ico" />
        <title>Service Status</title>
    </head>
    <body>

    <?php
    if ($isOnline)
    {
        echo "Online";
    }
    else
    {
        echo "Offline";
    }
    ?>
    </body>
</html>