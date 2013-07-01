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

package us.dustinj.locationstore.io;

import us.dustinj.locationstore.AppSettings;

public class RestInterface {
    private static final String urlPath = "/location/";

    public static String CheckApiKeyURL(AppSettings settings) {
        return getBaseURLWithApiKey(settings, "checkApiKey");
    }

    public static String GetExportDataURL(AppSettings settings) {
        return getBaseURLWithApiKey(settings, "getExportData");
    }

    public static String SetLocationDataURL(AppSettings settings) {
        return getBaseURLWithApiKey(settings, "setLocationData");
    }

    public static String MapURL(AppSettings settings, String exportID) {
        return getBaseURL(settings, "map") + "?exportID=" + exportID + "&count=20";
    }

    public static String MapURL(AppSettings settings, String exportID, long count) {
        return getBaseURL(settings, "map") + "?exportID=" + exportID + "&count=" + count;
    }

    public static String CreateExportID(AppSettings settings, long durationMs) {
        return getBaseURLWithApiKey(settings, "createExportID") + "&durationMs=" + durationMs;
    }

    public static String DeleteExportID(AppSettings settings, String exportID) {
        return getBaseURLWithApiKey(settings, "deleteExportID") + "&exportID=" + exportID;
    }

    public static String SetGcmRegistrationID(AppSettings settings, String gcmRegistrationID) {
        return getBaseURLWithApiKey(settings, "setGcmRegistrationID") + "&gcmRegistrationID=" + gcmRegistrationID;
    }

    private static String getBaseURLWithApiKey(AppSettings settings, String method) {
        return getBaseURL(settings, method) + "?apiKey=" + settings.GetApiKey();
    }

    private static String getBaseURL(AppSettings settings, String method) {
        return "http://" + settings.GetHostName() + urlPath + method + ".php";
    }
}
