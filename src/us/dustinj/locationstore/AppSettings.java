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

import us.dustinj.utilities.SimpleTimer;
import android.app.Activity;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.SharedPreferences.Editor;
import android.content.SharedPreferences.OnSharedPreferenceChangeListener;

public class AppSettings implements OnSharedPreferenceChangeListener {
    private static AppSettings m_settings = null;
    private static final String APP_SHARED_PREFS = "us.dustinj.locationstore_preferences"; // Name of the file -.xml
    private final SharedPreferences appSharedPrefs;
    private final TickManager m_tickManager;
    private final Timers m_timers;
    private boolean m_isClosed;

    public static AppSettings GetSettings(Context context)
    {
        if (m_settings == null) {
            synchronized (AppSettings.class) {
                if (m_settings == null) {
                    m_settings = new AppSettings(context);
                }
            }
        }

        return m_settings;
    }

    private AppSettings(Context context)
    {
        appSharedPrefs = context.getSharedPreferences(APP_SHARED_PREFS, Activity.MODE_PRIVATE);
        appSharedPrefs.registerOnSharedPreferenceChangeListener(this);
        m_tickManager = TickManager.GetTickManager(context);
        m_timers = new Timers();
        m_isClosed = false;

        m_timers.ExportPeriod.SetDuration(GetMinUploadPeriodMs());
        m_tickManager.AddRegistration(m_timers.ExportPeriod, m_timers.ExportPeriod.GetDuration());

        m_timers.ForcedFixPeriod.SetDuration(GetForcedFixPeriodMs());
        m_tickManager.AddRegistration(m_timers.ForcedFixPeriod, m_timers.ForcedFixPeriod.GetDuration());

        m_timers.GpsFixAcquirePeriod.SetDuration(5 * 60 * 1000);
        m_tickManager.AddRegistration(m_timers.GpsFixAcquirePeriod, m_timers.GpsFixAcquirePeriod.GetDuration());

        m_timers.GpsFixSettlePeriod.SetDuration(15 * 1000);

        m_timers.GcmRegistrationRetryPeriod.SetDuration(10 * 1000);
        m_tickManager.AddRegistration(m_timers.GcmRegistrationRetryPeriod, m_timers.GcmRegistrationRetryPeriod.GetDuration());
    }

    public int GetAccuracyThresholdM() {
        String valueStr = appSharedPrefs.getString("locationAccuracyPref", "");
        int value = 0;

        try {
            value = Integer.valueOf(valueStr);
        } catch (Exception e) {
            value = 150;
        }

        return value;
    }

    public int GetLocationSamplePeriodMs() {
        String valueStr = appSharedPrefs.getString("locationSampleRatePref", "");
        int value = 0;

        try {
            value = Integer.valueOf(valueStr);
        } catch (Exception e) {
            value = 15;
        }

        value = Math.max(Math.min(value, 120), 0) * 1000;

        return value;
    }

    public int GetMinUploadPeriodMs() {
        String valueStr = appSharedPrefs.getString("locationUploadPref", "");
        int value = 0;

        try {
            value = Integer.valueOf(valueStr) * 60 * 1000;
        } catch (Exception e) {
            value = 2 * 60 * 1000;
        }

        return value;
    }

    public boolean IsForcedFixEnabled() {
        return appSharedPrefs.getBoolean("enableForcedFixPref", true);
    }

    public int GetForcedFixPeriodMs() {
        String valueStr = appSharedPrefs.getString("forcedFixPref", "");
        int value = 0;

        try {
            value = Integer.valueOf(valueStr) * 60 * 1000;
        } catch (Exception e) {
            value = 10 * 60 * 1000;
        }

        return value;
    }

    public int GetForcedFixBatteryCutoff() {
        String valueStr = appSharedPrefs.getString("forcedFixBatteryCutoffPref", "");
        int value = 0;

        try {
            value = Integer.valueOf(valueStr);
        } catch (Exception e) {
            value = 15;
        }

        value = Math.max(Math.min(value, 95), 0);

        return value;
    }

    public String GetShareSubject() {
        return appSharedPrefs.getString("shareSubjectPref", "");
    }

    public String GetShareIntro() {
        return appSharedPrefs.getString("shareIntroPref", "");
    }

    public void ResetAdvancedSettingsToDefault() {
        Editor editor = appSharedPrefs.edit();
        editor.putString("locationAccuracyPref", "150");
        editor.putString("locationSampleRatePref", "10");
        editor.putString("locationUploadPref", "2");
        editor.putBoolean("enableForcedFixPref", true);
        editor.putString("forcedFixPref", "10");
        editor.putString("forcedFixBatteryCutoffPref", "15");
        editor.commit();
    }

    public void ResetGreetingsToDefault() {
        Editor editor = appSharedPrefs.edit();
        editor.putString("shareSubjectPref", "My Location");
        editor.putString("shareIntroPref", "My live location");
        editor.commit();
    }

    public String GetHostName() {
        return appSharedPrefs.getString("hostNamePref", "Dustinj.us");
    }

    public String GetApiKey() {
        return appSharedPrefs.getString("apiKeyPref", "Not Set");
    }

    public String GetGcmRegistrationID() {
        return appSharedPrefs.getString("gcmRegistrationIDPref", "");
    }

    public void SetGcmRegistrationID(String registrationID) {
        Editor editor = appSharedPrefs.edit();
        editor.putString("gcmRegistrationIDPref", registrationID);
        editor.commit();
    }

    public boolean IsGcmRegistrationIDCommunicatedToServer() {
        return appSharedPrefs.getBoolean("gcmRegistrationIDCommunicatedToServerPref", false);
    }

    public void SetGcmRegistrationIDCommunicatedToServer(boolean communicated) {
        Editor editor = appSharedPrefs.edit();
        editor.putBoolean("gcmRegistrationIDCommunicatedToServerPref", communicated);
        editor.commit();
    }

    public boolean IsClosed()
    {
        return m_isClosed;
    }

    public void Close()
    {
        if (!m_isClosed)
        {
            this.appSharedPrefs.unregisterOnSharedPreferenceChangeListener(this);
            m_isClosed = true;
        }
    }

    public Timers GetTimers() {
        return m_timers;
    }

    @Override
    public void finalize()
    {
        Close();
    }

    @Override
    public void onSharedPreferenceChanged(SharedPreferences sharedPreferences, String key)
    {
        if (key.equals("locationUploadPref"))
        {
            m_timers.ExportPeriod.SetDuration(GetMinUploadPeriodMs());
            m_tickManager.AddRegistration(m_timers.ExportPeriod, GetMinUploadPeriodMs());
        }
        else if (key.equals("forcedFixPref"))
        {
            m_timers.ForcedFixPeriod.SetDuration(GetForcedFixPeriodMs());
            m_tickManager.AddRegistration(m_timers.ForcedFixPeriod, GetForcedFixPeriodMs());
        }
    }

    public class Timers {
        public final SimpleTimer ExportPeriod = new SimpleTimer(0);
        public final SimpleTimer ForcedFixPeriod = new SimpleTimer(0);
        public final SimpleTimer GpsFixAcquirePeriod = new SimpleTimer(0);
        public final SimpleTimer GpsFixSettlePeriod = new SimpleTimer(0);
        public final SimpleTimer GcmRegistrationRetryPeriod = new SimpleTimer(0);

        private Timers() {
            ExportPeriod.SetGraceTimeMs(5 * 1000); // 5 seconds of slop
            ForcedFixPeriod.SetGraceTimeMs(5 * 1000);
            GpsFixAcquirePeriod.SetGraceTimeMs(5 * 1000);
            GpsFixSettlePeriod.SetGraceTimeMs(1 * 1000);
            GcmRegistrationRetryPeriod.SetGraceTimeMs(5 * 1000);
        }
    }

}
