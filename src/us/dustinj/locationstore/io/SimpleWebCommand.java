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

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;

import android.content.Context;
import android.os.AsyncTask;
import android.util.Log;

public class SimpleWebCommand extends AsyncTask<Void, Void, Integer> {

    private final HttpStatusResponseHandler m_responseReceiver;
    private final String m_commandURL;

    public SimpleWebCommand(Context context, String commandURL, HttpStatusResponseHandler responseReceiver) {
        m_responseReceiver = responseReceiver;
        m_commandURL = commandURL;
    }

    @Override
    protected Integer doInBackground(Void... Void) {
        int returnCode = 200;

        try {
            returnCode = get(new URL(m_commandURL));
        } catch (MalformedURLException e) {
            e.printStackTrace();
        }

        return Integer.valueOf(returnCode);
    }

    protected int get(URL url) {
        HttpURLConnection conn = null;
        int returnCode = 400;

        try {
            conn = (HttpURLConnection) url.openConnection();
            conn.setConnectTimeout(10000);

            conn.setDoOutput(false);
            conn.setDoInput(false);

            conn.connect();

            returnCode = conn.getResponseCode();
        } catch (IOException e) {
            try {
                returnCode = conn.getResponseCode();
            } catch (IOException e1) {
            }
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }

        return returnCode;
    }

    @Override
    protected void onPostExecute(Integer returnCode) {
        Log.d(this.getClass().getSimpleName(), "Return Code: " + returnCode.toString());
        m_responseReceiver.onResponseReceived(returnCode);
    }
}
