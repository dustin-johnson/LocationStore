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

package us.dustinj.locationstore;

import us.dustinj.utilities.CommonUtilities;
import android.content.Context;
import android.content.Intent;
import android.support.v4.content.LocalBroadcastManager;
import android.util.Log;

import com.google.android.gcm.GCMBaseIntentService;

public class GCMIntentService extends GCMBaseIntentService {

    public GCMIntentService() {
        super(CommonUtilities.SENDER_ID);
    }

    @Override
    protected void onRegistered(Context arg0, String registrationId) {
        Log.i(this.getClass().getSimpleName(), "onRegistered: regId = " + registrationId);

        Intent intent = new Intent("gcm_registrationIdChanged");
        intent.putExtra("registrationID", registrationId);
        LocalBroadcastManager.getInstance(this).sendBroadcast(intent);
    }

    @Override
    protected void onUnregistered(Context arg0, String arg1) {
        Log.i(this.getClass().getSimpleName(), "onUnregistered: " + arg1);
    }

    @Override
    protected void onMessage(Context arg0, Intent arg1) {
        Log.i(this.getClass().getSimpleName(), "onMessage");

        arg1.setAction("gcm_messageReceived");
        LocalBroadcastManager.getInstance(this).sendBroadcast(arg1);
    }

    @Override
    protected void onError(Context arg0, String errorId) {
        Log.i(this.getClass().getSimpleName(), "onError: " + errorId);
    }

    @Override
    protected boolean onRecoverableError(Context context, String errorId) {
        boolean temp = super.onRecoverableError(context, errorId);

        Log.i(this.getClass().getSimpleName(), "onRecoverableError: " + errorId);
        return temp;
    }
}
