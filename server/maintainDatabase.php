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
 *  Optimize the database and delete any exports that are over a week old (since their expiration date).
 */

optimizeDB();

$mysqli = connectToDB();
$mysqli->query("DELETE FROM exports WHERE (startTimestamp + durationMs + (7 * 24 * 3600 * 1000)) <= (UNIX_TIMESTAMP() * 1000)") or die($mysqli->error.__LINE__);
mysqli_close($mysqli);

?>