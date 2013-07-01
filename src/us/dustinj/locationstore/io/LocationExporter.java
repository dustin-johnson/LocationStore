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

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.UnsupportedEncodingException;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.util.HashMap;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import us.dustinj.locationstore.AppSettings;
import us.dustinj.locationstore.DatabaseField;
import us.dustinj.locationstore.LocationDatabase;
import android.content.Context;
import android.database.Cursor;
import android.os.AsyncTask;
import android.os.PowerManager;
import android.os.PowerManager.WakeLock;
import android.util.Log;

public class LocationExporter extends AsyncTask<Void, Void, Integer> {
    private final HttpStatusResponseHandler m_responseReceiver;
    private final AppSettings m_appPrefs;
    private final Context m_context;
    private final WakeLock m_wakeLock;

    public LocationExporter(Context context, HttpStatusResponseHandler responseReceiver) {
        m_context = context;
        m_responseReceiver = responseReceiver;
        m_appPrefs = AppSettings.GetSettings(context);

        PowerManager pm = (PowerManager) m_context.getSystemService(Context.POWER_SERVICE);
        m_wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, this.getClass().getName());
    }

    @Override
    public void onPreExecute() {
        m_wakeLock.acquire();
    }

    @Override
    protected Integer doInBackground(Void... Void) {
        int returnCode = 200;
        LocationDatabase locationDB = new LocationDatabase(m_context);

        long currentID = locationDB.GetCurrentID();
        Cursor cursor = locationDB.GetRecordsUpToID(currentID);
        Log.d(this.getClass().getSimpleName(), "Retrieved " + Integer.valueOf(cursor.getCount()) + " locations from the database");

        if (cursor.getCount() > 0)
        {
            JSONArray locations = getJSON(cursor);
            Log.d(this.getClass().getSimpleName(), locations.toString());

            try {
                returnCode = post(new URL(RestInterface.SetLocationDataURL(m_appPrefs)), locations.toString().getBytes("UTF8"));
            } catch (MalformedURLException e) {
                // TODO Auto-generated catch block
                e.printStackTrace();
            } catch (UnsupportedEncodingException e) {
                // TODO Auto-generated catch block
                e.printStackTrace();
            }

            if (returnCode / 100 == 2) {
                int removedRowCount = locationDB.DeleteRecords(currentID);
                Log.d(this.getClass().getSimpleName(), "Removed " + Integer.valueOf(removedRowCount) + " locations from the database");
            }
        }

        locationDB.close();

        return Integer.valueOf(returnCode);
    }

    private JSONArray getJSON(Cursor queryCursor)
    {
        JSONArray locations = new JSONArray();

        try {
            while (!queryCursor.isAfterLast())
            {
                JSONObject location = new JSONObject();
                HashMap<String, DatabaseField> databaseFields = LocationDatabase.GetColumnDefinitions();

                for (int columnIndex = 0; columnIndex < queryCursor.getColumnCount(); columnIndex++) {
                    DatabaseField currentField = databaseFields.get(queryCursor.getColumnName(columnIndex));

                    if (currentField.Type.equals("TEXT")) {
                        location.put(queryCursor.getColumnName(columnIndex), queryCursor.getString(columnIndex));
                    }
                    else if (currentField.Type.equals("REAL")) {
                        location.put(queryCursor.getColumnName(columnIndex), queryCursor.getDouble(columnIndex));
                    }
                    else if (currentField.Type.equals("INTEGER")) {
                        location.put(queryCursor.getColumnName(columnIndex), queryCursor.getInt(columnIndex));
                    }
                    else {
                        Log.e(this.getClass().getSimpleName(), "Read unsupported type from the database");
                    }
                }
                queryCursor.moveToNext();

                locations.put(location);
            }

        } catch (JSONException e) {
            // TODO Auto-generated catch block
            e.printStackTrace();
        }

        return locations;
    }

    protected int post(URL url, byte[] payload) {
        HttpURLConnection conn = null;
        int returnCode = 400;

        try {
            // this does no network IO.
            conn = (HttpURLConnection) url.openConnection();
            conn.setConnectTimeout(10000);

            // tells HUC that you're going to POST; still no IO.
            conn.setDoOutput(true);
            conn.setDoInput(true);
            conn.setFixedLengthStreamingMode(payload.length); // still no IO
            InputStream in;
            OutputStream out;

            // this opens a connection, then sends POST & headers.
            out = conn.getOutputStream();

            // At this point, the client may already have received a 4xx
            // or 5xx error, but don’t you dare call getResponseCode()
            // or HUC will hit you with an exception.

            // now we can send the body
            out.write(payload);

            // NOW you can look at the status.
            returnCode = conn.getResponseCode();
            if (returnCode / 100 != 2) {
                // Dear me, dear me
            }

            // presumably you’re interested in the response body
            // Unlike the identical call in the previous example, this provokes no network IO.
            in = conn.getInputStream();
            Log.d(this.getClass().getSimpleName(), convertStreamToString(in));
        } catch (IOException e) {
            try {
                returnCode = conn.getResponseCode();
            } catch (IOException e1) {
                e1.printStackTrace();
            }
        } finally {
            if (conn != null) {
                conn.disconnect(); // Let's practice good hygiene
            }
        }

        return returnCode;
    }

    private String convertStreamToString(InputStream is) {
        /*
         * To convert the InputStream to String we use the BufferedReader.readLine() method. We iterate until the BufferedReader return null which means there's no more data to read. Each line will
         * appended to a StringBuilder and returned as String.
         */
        BufferedReader reader = new BufferedReader(new InputStreamReader(is));
        StringBuilder sb = new StringBuilder();

        String line = null;
        try {
            while ((line = reader.readLine()) != null) {
                sb.append(line + "\n");
            }
        } catch (IOException e) {
            e.printStackTrace();
        } finally {
            try {
                is.close();
            } catch (IOException e) {
                e.printStackTrace();
            }
        }
        return sb.toString();
    }

    @Override
    protected void onPostExecute(Integer returnCode) {
        Log.d(this.getClass().getSimpleName(), "Return Code: " + returnCode.toString());
        m_responseReceiver.onResponseReceived(returnCode);
        m_wakeLock.release();
    }
}
