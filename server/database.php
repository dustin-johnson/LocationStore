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

include "configuationInformation.php";

function connectToDB()
{
    // All the connection variables for the next line are defined in configurationInformation.php
    $mysqli = new mysqli($dbLocation, $dbUsername, $dbPassword, $dbName);
    if (mysqli_connect_errno())
    {
        printf("DB connection failed: %s\n", mysqli_connect_error());
        exit();
    }
    
    return $mysqli;
}

function createLocationTable()
{
    $mysqli = connectToDB();

    $query = "CREATE TABLE locations(ID INT NOT NULL AUTO_INCREMENT,
                                     timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                     locationTimestamp BIGINT UNSIGNED,
                                     internalID VARCHAR(255),
                                     lat_deg DOUBLE,
                                     lon_deg DOUBLE,
                                     alt_m DOUBLE,
                                     accuracy_m DOUBLE,
                                     speed_mps DOUBLE,
                                     bearing_deg DOUBLE,
                                     provider VARCHAR(255),
                                     battery_percent FLOAT,
                                     PRIMARY KEY(ID),
                                     UNIQUE KEY(locationTimestamp, internalID, lat_deg, lon_deg));";
    $result = $mysqli->query($query) or die($mysqli->error.__LINE__);

    mysqli_close($mysqli);
}

function dropLocationTable()
{
    $mysqli = connectToDB();
    $result = $mysqli->query("DROP TABLE locations");
    mysqli_close($mysqli);
}

function createExportsTable()
{
    $mysqli = connectToDB();

    $query = "CREATE TABLE exports(ID INT NOT NULL AUTO_INCREMENT,
                                   timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                   exportID VARCHAR(25),
                                   internalID VARCHAR(255),
                                   modifiable BOOLEAN NOT NULL DEFAULT TRUE,
                                   active BOOLEAN NOT NULL DEFAULT TRUE,
                                   startTimestamp BIGINT UNSIGNED,
                                   durationMs BIGINT UNSIGNED,
                                   minLat_deg DOUBLE NOT NULL DEFAULT '-90.0',
                                   maxLat_deg DOUBLE NOT NULL DEFAULT '90.0',
                                   minLon_deg DOUBLE NOT NULL DEFAULT '-180.0',
                                   maxLon_deg DOUBLE NOT NULL DEFAULT '180.0',
                                   PRIMARY KEY(ID),
                                   KEY(exportID));";
    $result = $mysqli->query($query) or die($mysqli->error.__LINE__);

    mysqli_close($mysqli);
}

function dropExportsTable()
{
    $mysqli = connectToDB();
    $result = $mysqli->query("DROP TABLE exports");
    mysqli_close($mysqli);
}

function createUsersTable()
{
    $mysqli = connectToDB();

    $query = "CREATE TABLE users(ID INT NOT NULL AUTO_INCREMENT,
                                 timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                 userID VARCHAR(255),
                                 username VARCHAR(255),
                                 password VARCHAR(255),
                                 internalID VARCHAR(255),
                                 apiKey VARCHAR(255),
                                 gcmRegistrationID VARCHAR(255),
                                 PRIMARY KEY(ID),
                                 UNIQUE KEY(username),
                                 UNIQUE KEY(internalID),
                                 UNIQUE KEY(apiKey));";
    $result = $mysqli->query($query) or die($mysqli->error.__LINE__);

    mysqli_close($mysqli);
}

function dropUsersTable()
{
    $mysqli = connectToDB();
    $result = $mysqli->query("DROP TABLE users");
    mysqli_close($mysqli);
}

function createViewsTable()
{
    $mysqli = connectToDB();

    $query = "CREATE TABLE views(ID INT NOT NULL AUTO_INCREMENT,
                                 timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                 exportID VARCHAR(255),
                                 accessTimestamp BIGINT UNSIGNED,
                                 ipAddress VARCHAR(20),
                                 sentLocationFixRequest BOOLEAN NOT NULL DEFAULT FALSE,
                                 browserID VARCHAR(255),
                                 PRIMARY KEY(ID),
                                 KEY(exportID));";
    $result = $mysqli->query($query) or die($mysqli->error.__LINE__);

    mysqli_close($mysqli);
}

function dropViewsTable()
{
    $mysqli = connectToDB();
    $result = $mysqli->query("DROP TABLE views");
    mysqli_close($mysqli);
}

function optimizeDB()
{
    $mysqli = connectToDB();
    $alltables = $mysqli->query("SHOW TABLES");

    while ($table = $alltables->fetch_assoc())
    {
       foreach ($table as $db => $tablename)
       {
           $mysqli->query("OPTIMIZE TABLE ".$tablename."") or die($mysqli->error.__LINE__);
       }
    }

    mysqli_close($mysqli);
}

// This function will drop all tables and recreate them.  This will delete all user data.
function createTables()
{
    dropLocationTable();
    createLocationTable();

    dropExportsTable();
    createExportsTable();

    dropUsersTable();
    createUsersTable();

    dropViewsTable();
    createViewsTable();
}
?>