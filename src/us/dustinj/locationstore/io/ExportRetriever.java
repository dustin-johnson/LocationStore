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
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.util.ArrayList;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import org.json.JSONTokener;

import us.dustinj.locationstore.AppSettings;
import us.dustinj.locationstore.Export;
import android.content.Context;
import android.os.AsyncTask;
import android.util.Log;

public class ExportRetriever extends AsyncTask<Void, Void, ExportResponsePackage> {
    private final ExportResponseHandler m_responseReceiver;
    private final AppSettings m_appPrefs;

    public interface ExportResponseHandler {
        public abstract void onExportsReceived(int returnCode, ArrayList<Export> exports);
    }

    public ExportRetriever(Context context, ExportResponseHandler responseReceiver) {
        m_responseReceiver = responseReceiver;
        m_appPrefs = AppSettings.GetSettings(context);
    }

    @Override
    protected ExportResponsePackage doInBackground(Void... Void) {
        ExportResponsePackage response = new ExportResponsePackage();

        try {
            response = getExports(new URL(RestInterface.GetExportDataURL(m_appPrefs)));
        } catch (MalformedURLException e) {
            e.printStackTrace();
        }

        return response;
    }

    protected ExportResponsePackage getExports(URL url) {
        HttpURLConnection conn = null;
        ExportResponsePackage response = new ExportResponsePackage();

        try {
            String inputExports;

            conn = (HttpURLConnection) url.openConnection();
            conn.setConnectTimeout(10000);

            conn.setDoOutput(false);
            conn.setDoInput(true);
            conn.connect();
            response.ReturnCode = conn.getResponseCode();

            inputExports = convertStreamToString(conn.getInputStream());
            Log.d(this.getClass().getSimpleName(), inputExports);
            response.Exports = jsonToExports(inputExports);

        } catch (IOException e) {
            try {
                response.ReturnCode = conn.getResponseCode();
            } catch (IOException e1) {
                e1.printStackTrace();
            }
        } finally {
            if (conn != null) {
                conn.disconnect(); // Let's practice good hygiene
            }
        }

        return response;
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
    protected void onPostExecute(ExportResponsePackage response) {
        Log.d(this.getClass().getSimpleName(), "Return Code: " + response.ReturnCode.toString());
        m_responseReceiver.onExportsReceived(response.ReturnCode, response.Exports);
    }

    protected ArrayList<Export> jsonToExports(String json) {
        ArrayList<Export> exports = new ArrayList<Export>();

        try {
            JSONArray exportJsonArray = (JSONArray) new JSONTokener(json).nextValue();

            for (int i = 0; i < exportJsonArray.length(); i++) {
                Export export = new Export((JSONObject) exportJsonArray.get(i));
                exports.add(export);
            }

        } catch (JSONException e) {
        }

        return exports;
    }
}
