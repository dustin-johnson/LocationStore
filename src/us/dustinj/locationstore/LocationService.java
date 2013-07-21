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

import us.dustinj.locationstore.io.HttpStatusResponseHandler;
import us.dustinj.locationstore.io.LocationExporter;
import us.dustinj.locationstore.io.RestInterface;
import us.dustinj.locationstore.io.SimpleWebCommand;
import us.dustinj.utilities.CommonUtilities;
import android.app.Service;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.os.BatteryManager;
import android.os.Binder;
import android.os.Bundle;
import android.os.IBinder;
import android.provider.Settings;
import android.support.v4.content.LocalBroadcastManager;
import android.util.Log;

import com.google.android.gcm.GCMRegistrar;

public class LocationService extends Service implements LocationListener, HttpStatusResponseHandler {

    private LocationManager m_locationManager;
    private ConnectivityManager m_connectivityManager;
    private LocationDatabase m_locationDatabase;
    private LocationAggregator m_locationAggregator;
    private AppSettings m_appSettings;
    private ExportStateMachine m_exportStateMachine;
    private ForcedFixStateMachine m_forcedFixStateMachine;
    private GcmRegistrationStateMachine m_gcmRegistrationStateMachine;
    private final IBinder m_binder = new LocationBinder();

    public class LocationBinder extends Binder {
        LocationService getService() {
            return LocationService.this;
        }
    }

    @Override
    public IBinder onBind(Intent intent) {
        Log.d(this.getClass().getSimpleName(), "onBind");
        return m_binder;
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(this.getClass().getSimpleName(), "onStartCommand");

        Tick();

        return START_STICKY;
    }

    public synchronized void Tick() {
        Log.d(this.getClass().getSimpleName(), "Tick");

        m_locationAggregator.Tick();
        m_exportStateMachine.sendMessage(ExportStateMachine.Tick);
        m_forcedFixStateMachine.sendMessage(ForcedFixStateMachine.Tick);
        m_gcmRegistrationStateMachine.sendMessage(GcmRegistrationStateMachine.Tick);
    }

    public synchronized void ForceLocationFix() {
        Log.d(this.getClass().getSimpleName(), "ForceLocationFix");

        m_forcedFixStateMachine.sendMessage(ForcedFixStateMachine.ForceOneTimeFix);
        m_exportStateMachine.sendMessage(ExportStateMachine.ForceQuickUpload);
        Tick();
    }

    @Override
    public void onCreate() {
        Log.d(this.getClass().getSimpleName(), "onCreate");

        m_appSettings = AppSettings.GetSettings(this);
        m_locationDatabase = new LocationDatabase(this);
        m_locationAggregator = new LocationAggregator(m_appSettings, m_locationDatabase);
        m_exportStateMachine = ExportStateMachine.makeStateMachine(this, m_appSettings.GetTimers());
        m_forcedFixStateMachine = ForcedFixStateMachine.makeStateMachine(this, m_appSettings);
        m_gcmRegistrationStateMachine = GcmRegistrationStateMachine.makeStateMachine(this, m_appSettings.GetTimers());

        m_locationManager = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
        m_locationManager.requestLocationUpdates(LocationManager.PASSIVE_PROVIDER, 1000, 1, this);

        m_connectivityManager = (ConnectivityManager) this.getSystemService(Context.CONNECTIVITY_SERVICE);

        LocalBroadcastManager.getInstance(this).registerReceiver(m_gcmRegistrationChangeReceiver, new IntentFilter("gcm_registrationIdChanged"));
        LocalBroadcastManager.getInstance(this).registerReceiver(m_gcmMessageIntentReceiver, new IntentFilter("gcm_messageReceived"));

        GCMRegistrar.checkDevice(this);
        GCMRegistrar.checkManifest(this);
        final String regId = GCMRegistrar.getRegistrationId(this);
        if (regId.equals("")) {
            GCMRegistrar.register(this, CommonUtilities.SENDER_ID);
        } else {
            Log.v(this.getClass().getSimpleName(), "Already registered");
        }

        Log.d(this.getClass().getSimpleName(), "regId: " + regId);
    }

    @Override
    public void onDestroy() {
        Log.d(this.getClass().getSimpleName(), "onDestroy");

        m_appSettings.Close();
        LocalBroadcastManager.getInstance(this).unregisterReceiver(m_gcmRegistrationChangeReceiver);
        LocalBroadcastManager.getInstance(this).unregisterReceiver(m_gcmMessageIntentReceiver);

        super.onDestroy();
    }

    public boolean IsBatteryAboveThreshold() {
        return getBatteryPercent() > m_appSettings.GetForcedFixBatteryCutoff();
    }

    private float getBatteryPercent() {
        Intent batteryStatus = this.registerReceiver(null, new IntentFilter(Intent.ACTION_BATTERY_CHANGED));

        int level = batteryStatus.getIntExtra(BatteryManager.EXTRA_LEVEL, -1);
        int scale = batteryStatus.getIntExtra(BatteryManager.EXTRA_SCALE, -1);

        return level / (float) scale * 100;
    }

    public boolean TurnOnGPS() {
        Log.d(this.getClass().getSimpleName(), "TurnOnGPS");
        String allowedLocationProviders = Settings.Secure.getString(getContentResolver(), Settings.Secure.LOCATION_PROVIDERS_ALLOWED);

        if (allowedLocationProviders == null || !allowedLocationProviders.contains(LocationManager.GPS_PROVIDER)) {
            Log.d(this.getClass().getSimpleName(), "GPS is off");
            return false;
        }

        m_locationManager.requestLocationUpdates(LocationManager.GPS_PROVIDER, 1, (float) 0.1, this);

        return true;
    }

    public void TurnOffGPS() {
        Log.d(this.getClass().getSimpleName(), "TurnOffGPS");
        m_locationManager.removeUpdates(this);
    }

    public void FlushLocationAggregator() {
        m_locationAggregator.Flush();
        Tick();
    }

    public boolean IsNetworkConnected() {
        NetworkInfo info = m_connectivityManager.getActiveNetworkInfo();
        boolean connected = info != null && info.isConnectedOrConnecting();

        Log.d(this.getClass().getSimpleName(), "IsNetworkConnected - canGetInfo: " + (info != null ? "True" : "False") + "  connected: " + (connected ? "True" : "False"));

        return connected;
    }

    private final BroadcastReceiver m_gcmMessageIntentReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            String messageID = intent.getStringExtra("messageID");

            Log.i(LocationService.this.getClass().getSimpleName(), "Received GCM Message: messageID = " + messageID);

            if (messageID != null) {
                if (messageID.equals("ForceLocationFix")) {
                    ForceLocationFix();
                }
            }
        }
    };

    // Our handler for received Intents from GCM.
    private final BroadcastReceiver m_gcmRegistrationChangeReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            SetGcmRegistrationID(intent.getStringExtra("registrationID"));
        }
    };

    public void SetGcmRegistrationID(String registrationID) {
        m_appSettings.SetGcmRegistrationID(registrationID);
        m_appSettings.SetGcmRegistrationIDCommunicatedToServer(false);

        Tick();
    }

    public boolean IsGcmRegistrationIDCommunicatedToServer() {
        return m_appSettings.IsGcmRegistrationIDCommunicatedToServer();
    }

    public void SendGcmRegistrationIdUpdate() {
        Log.d("test", RestInterface.SetGcmRegistrationID(m_appSettings, m_appSettings.GetGcmRegistrationID()));
        new SimpleWebCommand(this, RestInterface.SetGcmRegistrationID(m_appSettings, m_appSettings.GetGcmRegistrationID()), new HttpStatusResponseHandler() {

            @Override
            public void onResponseReceived(int returnCode) {
                Log.d(LocationService.this.getClass().getSimpleName(), "SendGcmRegistrationIdUpdate - Response: " + returnCode);

                if (returnCode == 200) {
                    m_gcmRegistrationStateMachine.sendMessage(GcmRegistrationStateMachine.RegistrationSuccess);
                    m_appSettings.SetGcmRegistrationIDCommunicatedToServer(true);
                } else {
                    m_gcmRegistrationStateMachine.sendMessage(GcmRegistrationStateMachine.RegistrationFailure);
                }
            }
        }).execute();
    }

    public boolean ExportLocations() {
        if (m_locationDatabase.GetRecordCount() > 0) {
            new LocationExporter(this, this).execute();
            return true;
        }

        return false;
    }

    @Override
    public void onResponseReceived(int returnCode) {
        Log.d(this.getClass().getSimpleName(), "onResponseReceived");

        if (returnCode == 200) {
            m_exportStateMachine.sendMessage(ExportStateMachine.ExportSuccess);
        }
        else {
            m_exportStateMachine.sendMessage(ExportStateMachine.ExportFailure);
        }

        Tick();
    }

    @Override
    public void onLocationChanged(Location loc) {
        ExtendedLocation eLoc = new ExtendedLocation(loc);

        eLoc.setBatteryPercent(getBatteryPercent());
        if (eLoc.hasAccuracy()) {
            if (eLoc.getAccuracy() <= m_appSettings.GetAccuracyThresholdM() || m_appSettings.GetAccuracyThresholdM() == 0) {
                m_locationAggregator.InsertLocation(eLoc);
                m_forcedFixStateMachine.sendMessage(ForcedFixStateMachine.ReceivedLocation);
            }
            else {
                Log.d(this.getClass().getSimpleName(), "Rejected location as accuracy was above threshold: " + eLoc.getAccuracy() + " meters");
            }
        }
        else {
            Log.d(this.getClass().getSimpleName(), "Rejected location as its accuracy was not available");
        }

        if (eLoc.getProvider().equals(LocationManager.GPS_PROVIDER)) {
            m_forcedFixStateMachine.sendMessage(ForcedFixStateMachine.ReceivedGPSFix);
        }

        Tick();
    }

    @Override
    public void onProviderDisabled(String provider) {
        Log.d(this.getClass().getSimpleName(), "onProviderDisabled");
    }

    @Override
    public void onProviderEnabled(String provider) {
        Log.d(this.getClass().getSimpleName(), "onProviderEnabled");
    }

    @Override
    public void onStatusChanged(String provider, int status, Bundle extras) {
        Log.d(this.getClass().getSimpleName(), "onStatusChanged");
    }
}
