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

$inputDatabaseFilter = array('locationTimestamp'    => array('filter'  => FILTER_VALIDATE_INT,
                                                             'options' => array('min_range' => 0,
                                                                                'max_range' => PHP_INT_MAX)
                                                            ),
                             'minLocationTimestamp' => array('filter'  => FILTER_VALIDATE_INT,
                                                             'options' => array('min_range' => 0,
                                                                                'max_range' => PHP_INT_MAX)
                                                            ),
                             'maxLocationTimestamp' => array('filter'  => FILTER_VALIDATE_INT,
                                                             'options' => array('min_range' => 0,
                                                                                'max_range' => PHP_INT_MAX)
                                                             ),
                             'startTimestamp'       => array('filter'  => FILTER_VALIDATE_INT,
                                                             'options' => array('min_range' => 0,
                                                                                'max_range' => PHP_INT_MAX)
                                                            ),
                             'durationMs'           => array('filter'  => FILTER_VALIDATE_INT,
                                                             'options' => array('min_range' => -1,
                                                                                'max_range' => PHP_INT_MAX)
                                                             ),
                             'exportID'             => array('filter'  => FILTER_SANITIZE_STRING,
                                                             'flags'   => FILTER_FLAG_NO_ENCODE_QUOTES |
                                                                          FILTER_FLAG_STRIP_LOW        |
                                                                          FILTER_FLAG_STRIP_HIGH       |
                                                                          FILTER_FLAG_ENCODE_AMP
                                                            ),
                             'gcmRegistrationID'    => array('filter'  => FILTER_SANITIZE_STRING,
                                                             'flags'   => FILTER_FLAG_NO_ENCODE_QUOTES |
                                                                          FILTER_FLAG_STRIP_LOW        |
                                                                          FILTER_FLAG_STRIP_HIGH       |
                                                                          FILTER_FLAG_ENCODE_AMP
                                                            ),
                             'userID'               => array('filter'  => FILTER_SANITIZE_STRING,
                                                             'flags'   => FILTER_FLAG_NO_ENCODE_QUOTES |
                                                                          FILTER_FLAG_STRIP_LOW        |
                                                                          FILTER_FLAG_STRIP_HIGH       |
                                                                          FILTER_FLAG_ENCODE_AMP
                                                            ),
                             'apiKey'               => array('filter'  => FILTER_SANITIZE_STRING,
                                                             'flags'   => FILTER_FLAG_NO_ENCODE_QUOTES |
                                                                          FILTER_FLAG_STRIP_LOW        |
                                                                          FILTER_FLAG_STRIP_HIGH       |
                                                                          FILTER_FLAG_ENCODE_AMP
                                                            ),
                             'outputFormat'         => array('filter'  => FILTER_SANITIZE_STRING,
                                                             'flags'   => FILTER_FLAG_NO_ENCODE_QUOTES |
                                                                          FILTER_FLAG_STRIP_LOW        |
                                                                          FILTER_FLAG_STRIP_HIGH       |
                                                                          FILTER_FLAG_ENCODE_AMP
                                                            ),
                             'useGroundAlt'    => array('filter' => FILTER_VALIDATE_BOOLEAN,
                                                        'flags'  => FILTER_NULL_ON_FAILURE
                                                       ),
                             'lat_deg'         => array('filter' => FILTER_VALIDATE_FLOAT),
                                'maxLat_deg'   => array('filter' => FILTER_VALIDATE_FLOAT),
                                'minLat_deg'   => array('filter' => FILTER_VALIDATE_FLOAT),
                             'lon_deg'         => array('filter' => FILTER_VALIDATE_FLOAT),
                                'maxLon_deg'   => array('filter' => FILTER_VALIDATE_FLOAT),
                                'minLon_deg'   => array('filter' => FILTER_VALIDATE_FLOAT),
                             'alt_m'           => array('filter' => FILTER_VALIDATE_FLOAT),
                             'accuracy_m'      => array('filter' => FILTER_VALIDATE_FLOAT),
                             'minAccuracy_m'   => array('filter' => FILTER_VALIDATE_FLOAT),
                             'speed_mps'       => array('filter' => FILTER_VALIDATE_FLOAT),
                             'bearing_deg'     => array('filter' => FILTER_VALIDATE_FLOAT),
                             'provider'        => array('filter' => FILTER_SANITIZE_STRING,
                                                        'flags'  => FILTER_FLAG_NO_ENCODE_QUOTES |
                                                                    FILTER_FLAG_STRIP_LOW        |
                                                                    FILTER_FLAG_STRIP_HIGH       |
                                                                    FILTER_FLAG_ENCODE_AMP
                                                  ),
                             'battery_percent' => array('filter' => FILTER_VALIDATE_FLOAT)
                            );

$inputControlFilter = array('count'        => array('filter'  => FILTER_VALIDATE_INT,
                                                    'options' => array('min_range' => 1,
                                                                       'max_range' => PHP_INT_MAX)
                                                   ),
                            'debug'        => array('filter'  => FILTER_VALIDATE_BOOLEAN)
                           );

$inputAllFilter = array_merge($inputDatabaseFilter, $inputControlFilter);

$inputs = filter_input_array(INPUT_GET, $inputAllFilter);

$isInputMinLocationTimestampSet = false;
$inputMinLocationTimestamp = 0;
if (isset($_GET["minLocationTimestamp"]))
{
    $isInputMinLocationTimestampSet = true;
    $inputMinLocationTimestamp = $inputs["minLocationTimestamp"];
}

$isInputMaxLocationTimestampSet = false;
$inputMaxLocationTimestamp = PHP_INT_MAX - 24*60*60*1000; // Default to max minus a day.  This avoid a javascript rounding bug and edges us away from future edge cases (hopefully)
if (isset($_GET["maxLocationTimestamp"]))
{
    $isInputMaxLocationTimestampSet = true;
    $inputMaxLocationTimestamp = $inputs["maxLocationTimestamp"];
}

$isInputStartTimestampSet = false;
$inputStartTimestamp = time() * 1000;
if (isset($_GET["startTimestamp"]))
{
    $isInputStartTimestampSet = true;
    $inputStartTimestamp = $inputs["startTimestamp"];
}

$isInputDurationMsSet = false;
$inputDurationMs = 2 * 60 * 60 * 1000; // 2 hour default
if (isset($_GET["durationMs"]))
{
    $isInputDurationMsSet = true;
    $inputDurationMs = $inputs["durationMs"];
}

$isInputExportIDSet = false;
$inputExportID = "";
if (isset($_GET["exportID"]))
{
    $isInputExportIDSet = true;
    $inputExportID = $inputs["exportID"];
}

$isInputGcmRegistrationIDSet = false;
$inputGcmRegistrationID = "";
if (isset($_GET["gcmRegistrationID"]))
{
    $isInputGcmRegistrationIDSet = true;
    $inputGcmRegistrationID = $inputs["gcmRegistrationID"];
}

$isInputUserIDSet = false;
$inputUserID = "";
if (isset($_GET["userID"]))
{
    $isInputUserIDSet = true;
    $inputUserID = $inputs["userID"];
}

$isInputApiKeySet = false;
$inputApiKey = "";
if (isset($_GET["apiKey"]))
{
    $isInputApiKeySet = true;
    $inputApiKey = $inputs["apiKey"];
}

$isInputOutputFormatSet = false;
$inputOutputFormat = "json";
if (isset($_GET["outputFormat"]))
{
    $isInputOutputFormatSet = true;
    $inputOutputFormat = $inputs["outputFormat"];
}

$isInputUseGroundAltSet = false;
$inputUseGroundAlt = true;
if (isset($_GET["useGroundAlt"]))
{
    $isInputUseGroundAltSet = true;
    $inputUseGroundAlt = $inputs["useGroundAlt"];
}

$isInputLatSet = false;
$inputLat_deg = 0.0;
if (isset($_GET["lat_deg"]) && $inputs["lat_deg"] >= -90.0 && $inputs["lat_deg"] <= 90.0)
{
    $isInputLatSet = true;
    $inputLat_deg = $inputs["lat_deg"];
}

$isInputMaxLatSet = false;
$inputMaxLat_deg = 90.0;
if (isset($_GET["maxLat_deg"]) && $inputs["maxLat_deg"] >= -90.0 && $inputs["maxLat_deg"] <= 90.0)
{
    $isInputMaxLatSet = true;
    $inputMaxLat_deg = $inputs["maxLat_deg"];
}

$isInputMinLatSet = false;
$inputMinLat_deg = -90.0;
if (isset($_GET["minLat_deg"]) && $inputs["minLat_deg"] >= -90.0 && $inputs["minLat_deg"] <= 90.0)
{
    $isInputMinLatSet = true;
    $inputMinLat_deg = $inputs["minLat_deg"];
}

$isInputLonSet = false;
$inputLon_deg = 0.0;
if (isset($_GET["lon_deg"]) && $inputs["lon_deg"] >= -180.0 && $inputs["lon_deg"] <= 180.0)
{
    $isInputLonSet = true;
    $inputLon_deg = $inputs["lon_deg"];
}

$isInputMaxLonSet = false;
$inputMaxLon_deg = 180.0;
if (isset($_GET["maxLon_deg"]) && $inputs["maxLon_deg"] >= -180.0 && $inputs["maxLon_deg"] <= 180.0)
{
    $isInputMaxLonSet = true;
    $inputMaxLon_deg = $inputs["maxLon_deg"];
}

$isInputMinLonSet = false;
$inputMinLon_deg = -180.0;
if (isset($_GET["minLon_deg"]) && $inputs["minLon_deg"] >= -180.0 && $inputs["minLon_deg"] <= 180.0)
{
    $isInputMinLonSet = true;
    $inputMinLon_deg = $inputs["minLon_deg"];
}

$isInputMinAccuracySet = false;
$inputMinAccuracy_m = 10000; // 10 km
if (isset($_GET["minAccuracy_m"]))
{
    $isInputMinAccuracySet = true;
    $inputMinAccuracy_m = $inputs["minAccuracy_m"];
}

$isInputCountSet = false;
$inputCount = 20;
if (isset($_GET["count"]))
{
    $isInputCountSet = true;
    $inputCount = $inputs["count"];
}

$debug = false;
if (isset($_GET["debug"]))
{
    $debug = $inputs["debug"];
}

if (!empty($includeInputsAsJavascript) && $includeInputsAsJavascript)
{
    echo "<script>\r\n";
    echo "    var isInputMinLocationTimestampSet = \"".$isInputMinLocationTimestampSet."\";\r\n";
    echo "    var inputMinLocationTimestamp = ".$inputMinLocationTimestamp.";\r\n";
    echo "    var isInputMaxLocationTimestampSet = \"".$isInputMaxLocationTimestampSet."\";\r\n";
    echo "    var inputMaxLocationTimestamp = ".$inputMaxLocationTimestamp.";\r\n";
    echo "    var isInputStartTimestampSet = \"".$isInputStartTimestampSet."\";\r\n";
    echo "    var inputStartTimestamp = ".$inputStartTimestamp.";\r\n";
    echo "    var isInputDurationMsSet = \"".$isInputDurationMsSet."\";\r\n";
    echo "    var inputDurationMs = ".$inputDurationMs.";\r\n";
    echo "    var isInputExportIDSet = \"".$isInputExportIDSet."\";\r\n";
    echo "    var inputExportID = \"".$inputExportID."\";\r\n";
    echo "    var isInputGcmRegistrationIDSet = \"".$isInputGcmRegistrationIDSet."\";\r\n";
    echo "    var inputGcmRegistrationID = \"".$inputGcmRegistrationID."\";\r\n";
    echo "    var isInputUserIDSet = \"".$isInputUserIDSet."\";\r\n";
    echo "    var inputUserID = \"".$inputUserID."\";\r\n";
    echo "    var isInputApiKeySet = \"".$isInputApiKeySet."\";\r\n";
    echo "    var inputApiKey = \"".$inputApiKey."\";\r\n";
    echo "    var isInputOutputFormatSet = \"".$isInputOutputFormatSet."\";\r\n";
    echo "    var inputOutputFormat = \"".$inputOutputFormat."\";\r\n";
    echo "    var isInputUseGroundAltSet = \"".$isInputUseGroundAltSet."\";\r\n";
    echo "    var inputUseGroundAlt = \"".$inputUseGroundAlt."\";\r\n";

    echo "    var isInputLatSet = \"".$isInputLatSet."\";\r\n";
    echo "    var inputLat_deg = ".$inputLat_deg.";\r\n";
    echo "    var isInputMaxLatSet = \"".$isInputMaxLatSet."\";\r\n";
    echo "    var inputMaxLat_deg = ".$inputMaxLat_deg.";\r\n";
    echo "    var isInputMinLatSet = \"".$isInputMinLatSet."\";\r\n";
    echo "    var inputMinLat_deg = ".$inputMinLat_deg.";\r\n";

    echo "    var isInputLonSet = \"".$isInputLonSet."\";\r\n";
    echo "    var inputLon_deg = ".$inputLon_deg.";\r\n";
    echo "    var isInputMaxLonSet = \"".$isInputMaxLonSet."\";\r\n";
    echo "    var inputMaxLon_deg = ".$inputMaxLon_deg.";\r\n";
    echo "    var isInputMinLonSet = \"".$isInputMinLonSet."\";\r\n";
    echo "    var inputMinLon_deg = ".$inputMinLon_deg.";\r\n";

    echo "    var isInputMinAccuracySet = \"".$isInputMinAccuracySet."\";\r\n";
    echo "    var inputMinAccuracy_m = ".$inputMinAccuracy_m.";\r\n";
    echo "    var isInputCountSet = \"".$isInputCountSet."\";\r\n";
    echo "    var inputCount = ".$inputCount.";\r\n";
    echo "    var debug = \"".$debug."\";\r\n";
    echo "</script>\r\n";
}

$inputJson = array();
$tmpInputJson = json_decode(file_get_contents('php://input'));
if (!empty($tmpInputJson) && is_array($tmpInputJson))
{
    foreach($tmpInputJson as $tmpInput)
    {
        $filteredInput = filter_var_array(objectToArray($tmpInput), $inputDatabaseFilter);
        array_push($inputJson, $filteredInput);
    }
}

function objectToArray($d)
{
    if (is_object($d))
    {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }

    if (is_array($d))
    {
        /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant)
        * for recursive call
        */
        return array_map(__FUNCTION__, $d);
    }
    else
    {
        // Return array
        return $d;
    }
}
?>