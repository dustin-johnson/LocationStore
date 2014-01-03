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

// Output JSON format
//
// {
//     "error": "Error text, if needed",
//     "userID": "Puck",
//     "bounds": {
//         "southWest": {
//             "lat_rad": 0.79779000555,
//             "lon_rad": -2.1208241258
//         },
//         "northEast": {
//             "lat_rad": 0.797791836151,
//             "lon_rad": -2.1208241258
//         }
//     },
//     "locations": [
//         {
//             Database Row
//         },
//         {
//             Database Row
//         }
//     ]
// }

include "database.php";
include "input.php";

$mysqli = connectToDB();
$LocationData = array();

$exportResult = $mysqli->query("SELECT * FROM exports WHERE active = TRUE AND exportID = \"".$mysqli->real_escape_string($inputExportID)."\"") or die($mysqli->error.__LINE__);
if($exportResult->num_rows > 0)
{
    $exportRow = $exportResult->fetch_assoc();
    $usersResult = $mysqli->query("SELECT * FROM users WHERE internalID = \"".$mysqli->real_escape_string($exportRow['internalID'])."\"") or die($mysqli->error.__LINE__);
    if($usersResult->num_rows > 0)
    {
        $usersRow = $usersResult->fetch_assoc();
        $LocationData['userID'] = $usersRow['userID'];
        $exportWhereClause = "internalID = \"" . $mysqli->real_escape_string($exportRow['internalID'])."\" and " .
                             "locationTimestamp >= " . $mysqli->real_escape_string($exportRow['startTimestamp']) . " and " .
                             "locationTimestamp <= " . $mysqli->real_escape_string($exportRow['startTimestamp'] + $exportRow['durationMs']) . " and " .
                             "lat_deg >= " . $mysqli->real_escape_string($exportRow['minLat_deg']) . " and " .
                             "lat_deg <= " . $mysqli->real_escape_string($exportRow['maxLat_deg']) . " and " .
                             "lon_deg >= " . $mysqli->real_escape_string($exportRow['minLon_deg']) . " and " .
                             "lon_deg <= " . $mysqli->real_escape_string($exportRow['maxLon_deg']);

        $inputWhereClause = "locationTimestamp >= \"" . $mysqli->real_escape_string($inputMinLocationTimestamp) . "\" and " .
                            "locationTimestamp <= \"" . $mysqli->real_escape_string($inputMaxLocationTimestamp) . "\" and " .
                            "lat_deg >= \"" . $mysqli->real_escape_string($inputMinLat_deg) . "\" and " .
                            "lat_deg <= \"" . $mysqli->real_escape_string($inputMaxLat_deg) . "\" and " .
                            "lon_deg >= \"" . $mysqli->real_escape_string($inputMinLon_deg) . "\" and " .
                            "lon_deg <= \"" . $mysqli->real_escape_string($inputMaxLon_deg) . "\" and " .
                            "accuracy_m <= \"" . $inputMinAccuracy_m . "\"";

        $locationResult = $mysqli->query("SELECT * FROM (SELECT * FROM locations WHERE ".$exportWhereClause." and ".$inputWhereClause." ORDER BY locationTimestamp DESC LIMIT ".$mysqli->real_escape_string($inputCount).") AS T ORDER BY locationTimestamp ASC") or die($mysqli->error.__LINE__);
        if($locationResult->num_rows > 0)
        {
            $columnsToExport = array('locationTimestamp',
                                     'lat_deg',
                                     'lon_deg',
                                     'alt_m',
                                     'accuracy_m',
                                     'speed_mps',
                                     'bearing_deg',
                                     'provider',
                                     'battery_percent');
            $Locations = array();
            $Bounds = array();

            $maxLatDeg = -90.0;
            $minLatDeg = 90.0;
            $maxLonDeg = -180.0;
            $minLonDeg = 180.0;

            while($locationRow = $locationResult->fetch_assoc())
            {
                $location = array();
                foreach($columnsToExport as $column)
                {
                    copyToArray($locationRow, $location, $column);
                }
                array_push($Locations, $location);

                $maxLatDeg = max($maxLatDeg, $locationRow['lat_deg']);
                $minLatDeg = min($minLatDeg, $locationRow['lat_deg']);
                $maxLonDeg = max($maxLonDeg, $locationRow['lon_deg']);
                $minLonDeg = min($minLonDeg, $locationRow['lon_deg']);
            }

            // Make sure we don't zoom in too far.
            $minViewPortDistance = 500; // meters
            if (distanceInMeters($minLatDeg / 180 * pi(),
                                 $minLonDeg / 180 * pi(),
                                 $maxLatDeg / 180 * pi(),
                                 $maxLonDeg / 180 * pi()) < $minViewPortDistance)
            {
                $centerLatDeg = ($minLatDeg + $maxLatDeg) / 2;
                $centerLonDeg = ($minLonDeg + $maxLonDeg) / 2;

                $southWest = getPositionByDistance($minViewPortDistance/2, 225, $centerLatDeg, $centerLonDeg);
                $northEast = getPositionByDistance($minViewPortDistance/2,  45, $centerLatDeg, $centerLonDeg);

                $minLatDeg = $southWest['lat'];
                $minLonDeg = $southWest['lon'];

                $maxLatDeg = $northEast['lat'];
                $maxLonDeg = $northEast['lon'];
            }

            $boundSouthWest = array();
            $boundSouthWest['lat_deg'] = $minLatDeg;
            $boundSouthWest['lon_deg'] = $minLonDeg;
            $Bounds['southWest'] = $boundSouthWest;

            $boundNorthEast = array();
            $boundNorthEast['lat_deg'] = $maxLatDeg;
            $boundNorthEast['lon_deg'] = $maxLonDeg;
            $Bounds['northEast'] = $boundNorthEast;

            $LocationData['bounds'] = $Bounds;
            $LocationData['locations'] = $Locations;
        }

        mysqli_free_result($locationResult);
    }
    else
    {
        $LocationData['error'] = "Internal Error: User table corrupted";
    }
}
else
{
    $LocationData['error'] = "Requested map does not exist or has expired";
}

mysqli_free_result($exportResult);

if ($inputOutputFormat == "gpx")
{
    header("Content-disposition: attachment; filename=\"".$LocationData['userID']."_".date("Y-m-d\TH:i:s\Z").".gpx\"");
    header("Content-type: application/xml"); 

    // Header
    echo "<?xml version=\"1.0\"?>\r\n";
    echo "<gpx version=\"1.1\" creator=\"Dustinj.us\" xmlns=\"http://www.topografix.com/GPX/1/1\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd\">\r\n";

    echo "  <trk>\r\n";
    echo "    <name>".htmlspecialchars($LocationData['userID'], ENT_QUOTES)."'s Location</name>\r\n";
    echo "    <trkseg>\r\n";

    $locations = $LocationData['locations'];
    $previousAlt = null;
    foreach($locations as $location)
    {
        echo "      <trkpt lat=\"".$location['lat_deg']."\" lon=\"".$location['lon_deg']."\">\r\n";
        if (isset($location['alt_m']))
        {
            $previousAlt = $location['alt_m'];
        }

        if (isset($previousAlt))
        {
            echo "        <ele>".$previousAlt."</ele>\r\n";
        }
        echo "        <time>".date("Y-m-d\TH:i:s\Z", $location['locationTimestamp']/1000)."</time>\r\n";
        echo "      </trkpt>\r\n";
    }

    echo "    </trkseg>\r\n";
    echo "  </trk>\r\n";
    echo "</gpx>\r\n";
}
else if ($inputOutputFormat == "kml")
{
    header("Content-disposition: attachment; filename=\"".$LocationData['userID']."_".date("Y-m-d\TH:i:s\Z").".kml\"");
    header("Content-type: application/xml"); 

    // Header
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n";
    echo "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:gx=\"http://www.google.com/kml/ext/2.2\">\r\n";
    echo "<Document>\r\n";
    echo "  <Style id=\"default\">\r\n";
    echo "    <LineStyle>\r\n";
    echo "      <color>ff7f0000</color>\r\n";
    echo "      <width>3</width>\r\n";
    echo "    </LineStyle>\r\n";
    echo "  </Style>\r\n";
    echo "  <StyleMap id=\"default0\">\r\n";
    echo "    <Pair>\r\n";
    echo "      <key>normal</key>\r\n";
    echo "      <styleUrl>#default</styleUrl>\r\n";
    echo "    </Pair>\r\n";
    echo "    <Pair>\r\n";
    echo "      <key>highlight</key>\r\n";
    echo "      <styleUrl>#hl</styleUrl>\r\n";
    echo "    </Pair>\r\n";
    echo "  </StyleMap>\r\n";
    echo "  <Style id=\"hl\">\r\n";
    echo "    <IconStyle>\r\n";
    echo "      <scale>1.2</scale>\r\n";
    echo "    </IconStyle>\r\n";
    echo "    <LineStyle>\r\n";
    echo "      <color>ff7f0000</color>\r\n";
    echo "      <width>3</width>\r\n";
    echo "    </LineStyle>\r\n";
    echo "  </Style>\r\n";
    echo "  <Folder>\r\n";
    echo "    <Placemark>\r\n";
    echo "      <name>".htmlspecialchars($LocationData['userID'], ENT_QUOTES)."'s Location</name>\r\n";
    echo "      <styleUrl>#default0</styleUrl>\r\n";
    echo "      <gx:Track>\r\n";

    if ($inputUseGroundAlt)
    {
        echo "        <!--altitudeMode>absolute</altitudeMode-->\r\n";
    }
    else
    {
        echo "        <altitudeMode>absolute</altitudeMode>\r\n";
    }

    $locations = $LocationData['locations'];
    $previousAlt = 0;
    foreach($locations as $location)
    {
        echo "        <when>".date("Y-m-d\TH:i:s\Z", $location['locationTimestamp']/1000)."</when>\r\n";
        if (isset($location['alt_m']))
        {
            $previousAlt = $location['alt_m'];
        }
        echo "        <gx:coord>".$location['lon_deg']." ".$location['lat_deg']." ".$previousAlt."</gx:coord>\r\n";
    }

    echo "      </gx:Track>\r\n";
    echo "    </Placemark>\r\n";
    echo "  </Folder>\r\n";
    echo "</Document>\r\n";
    echo "</kml>\r\n";
}
else
{
    header("Content-type: application/json"); 
    echo json_encode($LocationData, JSON_NUMERIC_CHECK | ($debug ? JSON_PRETTY_PRINT : 0));
}

mysqli_close($mysqli);

// Used to filter out the null values present in the database
function copyToArray(&$srcArray, &$dstArray, $key)
{
    if ($srcArray[$key] != null) $dstArray[$key] = $srcArray[$key];
}

function distanceInMeters($lat1, $lon1, $lat2, $lon2)
{
    $radiusOfEarth = 6371000; // Meters
    $dLat = $lat2 - $lat1;
    $dLon = $lon2 - $lon1;
    $a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));

    return $radiusOfEarth * $c;
}

/*
 * Find a point a certain distance and vector away from an initial point
 * converted from c function found at: http://sam.ucsd.edu/sio210/propseawater/ppsw_c/gcdist.c
 * 
 * @param int distance in meters
 * @param double direction in degrees i.e. 0 = North, 90 = East, etc.
 * @param double lon starting longitude in degrees
 * @param double lat starting latitude in degrees
 * @return array ('lon' => $lon, 'lat' => $lat)
 */
function getPositionByDistance($distance, $direction, $lat, $lon)
{
    $metersPerDegree = 111120.00071117;
    $degreesPerMeter = 1.0 / $metersPerDegree;
    $radiansPerDegree = pi() / 180.0;
    $degreesPerRadian = 180.0 / pi();

    if ($distance > $metersPerDegree*180)
    {
        $direction -= 180.0;
        if ($direction < 0.0)
        {
            $direction += 360.0;
        }
        $distance = $metersPerDegree * 360.0 - $distance;
    }

    if ($direction > 180.0)
    {
        $direction -= 360.0;
    }

    $c = $direction * $radiansPerDegree;
    $d = $distance * $degreesPerMeter * $radiansPerDegree;
    $L1 = $lat * $radiansPerDegree;
    $lon *= $radiansPerDegree;
    $coL1 = (90.0 - $lat) * $radiansPerDegree;
    $coL2 = ahav(hav($c) / (sec($L1) * csc($d)) + hav($d - $coL1));
    $L2   = (pi() / 2) - $coL2;
    $l    = $L2 - $L1;

    $dLo = (cos($L1) * cos($L2));
    if ($dLo != 0.0)
    {
        $dLo  = ahav((hav($d) - hav($l)) / $dLo);
    }

    if ($c < 0.0) 
    {
        $dLo = -$dLo;
    }

    $lon += $dLo;
    if ($lon < -pi())
    {
        $lon += 2 * pi();
    }
    elseif ($lon > pi())
    {
        $lon -= 2 * pi();
    }

    $xlat = $L2 * $degreesPerRadian;
    $xlon = $lon * $degreesPerRadian;

    return array('lon' => $xlon, 'lat' => $xlat);
}

function copysign($x, $y) { return ((($y) < 0.0) ? - abs($x) : abs($x)); }
function ngt1($x) { return (abs($x) > 1.0 ? copysign(1.0 , $x) : ($x)); }
function hav($x) { return ((1.0 - cos($x)) * 0.5); }
function ahav($x) { return acos(ngt1(1.0 - ($x * 2.0))); }
function sec($x) { return (1.0 / cos($x)); }
function csc($x) { return (1.0 / sin($x)); }

?>