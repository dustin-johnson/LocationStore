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

 // Database connection information
 {
    // The hostname or IP address of the SQL server
    $dbLocation = "localhost";

    // The username that will be used to login to the SQL server. This user needs read/write privileges to the entire
    // database defined by $dbName, including the ability to create and delete tables.
    $dbUsername = "locStore";

    // Change this password to match the password entered for the username defined by $dbUsername.
    $dbPassword = "replace_with_actual_password";

    // The database name that will house all of the tables needed for this application.
    $dbName = "locationStore";
}

// Google Cloud Messaging (GCM) variables
{
    // Set POST variables
    $gcmUrl = "https://android.googleapis.com/gcm/send";

    // BROWSER API key from Google APIs
    $gcmApiKey = "replace_with_actual_api_key";
}