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
include "configuationInformation.php";
include "database.php";
include "input.php";

http_response_code(500); // Default to 'Internal Server Error'
$mysqli = connectToDB();

$maxRequestRate = 2 * 60 * 1000; // ms
$locationFixRequested = false;

if ($isInputExportIDSet)
{
    $exportResult = $mysqli->query("SELECT * FROM exports WHERE exportID = \"".$mysqli->real_escape_string($inputExportID)."\"") or die($mysqli->error.__LINE__);
    if($exportResult->num_rows > 0)
    {
        $exportRow = $exportResult->fetch_assoc();

        // Check to make sure the current export is valid right now
        if (($exportRow['startTimestamp'] <= (time() * 1000)) && (($exportRow['startTimestamp'] + $exportRow['durationMs']) > time() * 1000))
        {
            // Check to make sure we haven't requested a location recently
            $viewsResult = $mysqli->query("SELECT * FROM views WHERE sentLocationFixRequest = 1 AND exportID = \"".$mysqli->real_escape_string($inputExportID)."\" ORDER BY accessTimestamp DESC LIMIT 1") or die($mysqli->error.__LINE__);
            $viewsRow = $viewsResult->fetch_assoc();
            if($viewsResult->num_rows == 0 || ($viewsRow['accessTimestamp'] + $maxRequestRate) <= (time() * 1000))
            {
                $usersResult = $mysqli->query("SELECT * FROM users WHERE internalID = \"".$mysqli->real_escape_string($exportRow['internalID'])."\"") or die($mysqli->error.__LINE__);
                if($usersResult->num_rows > 0)
                {
                    $usersRow = $usersResult->fetch_assoc();

                    $result = sendGcmMessage($usersRow['gcmRegistrationID'], "ForceLocationFix");
                    $locationFixRequested = true;

                    http_response_code(200); // Success
                    echo $result;
                }
                else
                {
                    http_response_code(401); // Unauthorized
                }
            }
            else
            {
                http_response_code(401); // Unauthorized
            }
        }
        else
        {
            http_response_code(401); // Unauthorized
        }
    }
    else
    {
        http_response_code(401); // Unauthorized
    }

    $result = $mysqli->query("INSERT INTO views (exportID, accessTimestamp, ipAddress, sentLocationFixRequest, browserID) " .
                                "VALUES (\"".$mysqli->real_escape_string($inputExportID)."\", \"".
                                             (time() * 1000)."\", \"".
                                             $mysqli->real_escape_string($_SERVER["REMOTE_ADDR"])."\", \"".
                                             $locationFixRequested."\", \"".
                                             $mysqli->real_escape_string($_SERVER['HTTP_USER_AGENT'])."\")");
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
else
{
    http_response_code(401); // Unauthorized, not enough information passed in
}

mysqli_close($mysqli);

function sendGcmMessage($registrationID, $message)
{
    // The below variables are defined in configurationInformation.php.
    $url = $gcmUrl;
    $apiKey = $gcmApiKey;

    $fields = array(
                        'registration_ids' => array($registrationID),
                        'collapse_key'     => $message,
                        'data'             => array("messageID" => $message)
                    );

    $headers = array( 
                        'Authorization: key=' . $apiKey,
                        'Content-Type: application/json'
                    );

    // Open connection
    $ch = curl_init();

    // Set the url, number of POST vars, POST data
    curl_setopt( $ch, CURLOPT_URL, $url );

    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );

    // Execute post
    $result = curl_exec($ch);

    // Close connection
    curl_close($ch);
    
    return $result;
}
?>