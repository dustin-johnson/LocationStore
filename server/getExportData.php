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

$mysqli = connectToDB();
$ExportData = array();
$exportWhereClause = "";
$exportResult = null;

if (strlen($inputExportID) > 0)
{
    $exportResult = $mysqli->query("SELECT * FROM exports WHERE active = TRUE AND exportID = \"".$mysqli->real_escape_string($inputExportID)."\"") or die($mysqli->error.__LINE__);
}
else if (strlen($inputApiKey) > 0)
{
    $exportResult = $mysqli->query("SELECT * FROM users, exports WHERE exports.active = TRUE AND users.internalID = exports.internalID AND users.apiKey = \"".$mysqli->real_escape_string($inputApiKey)."\"") or die($mysqli->error.__LINE__);
}

if(isset($exportResult))
{
    if ($exportResult->num_rows > 0)
    {
        $columnsToExport = array('exportID',
                                 'startTimestamp',
                                 'durationMs');

        while($exportRow = $exportResult->fetch_assoc())
        {
            $export = array();
            foreach($columnsToExport as $column)
            {
                copyToArray($exportRow, $export, $column);
            }
            array_push($ExportData, $export);
        }
    }
    mysqli_free_result($exportResult);
}

header("Content-type: application/json"); 
echo json_encode($ExportData, JSON_NUMERIC_CHECK | ($debug ? JSON_PRETTY_PRINT : 0));

mysqli_close($mysqli);

// Used to filter out the null values present in the database
function copyToArray(&$srcArray, &$dstArray, $key)
{
    if ($srcArray[$key] != null) $dstArray[$key] = $srcArray[$key];
}

?>