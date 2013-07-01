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
import us.dustinj.locationstore.io.RestInterface;
import us.dustinj.locationstore.io.SimpleWebCommand;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.ServiceConnection;
import android.content.SharedPreferences;
import android.content.SharedPreferences.OnSharedPreferenceChangeListener;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.content.pm.PackageManager.NameNotFoundException;
import android.net.Uri;
import android.os.Bundle;
import android.os.IBinder;
import android.preference.CheckBoxPreference;
import android.preference.EditTextPreference;
import android.preference.Preference;
import android.preference.Preference.OnPreferenceClickListener;
import android.preference.PreferenceActivity;
import android.preference.PreferenceManager;
import android.util.Log;
import android.widget.Toast;

public class SettingsActivity extends PreferenceActivity implements OnSharedPreferenceChangeListener, HttpStatusResponseHandler {
    public static final String KEY_API_KEY = "apiKeyPref";
    public static final String KEY_TEST_CONNECTION = "testConnectionPref";
    public static final String KEY_SEND_FEEDBACK = "sendFeedbackPref";

    public static final String KEY_LOCATION_ACCURACY_THRESHOLD = "locationAccuracyPref";
    public static final String KEY_LOCATION_SAMPLE_PERIOD = "locationSampleRatePref";
    public static final String KEY_LOCATION_UPLOAD_PERIOD = "locationUploadPref";
    public static final String KEY_FORCED_FIX_ENABLE = "enableForcedFixPref";
    public static final String KEY_FORCED_FIX_PERIOD = "forcedFixPref";
    public static final String KEY_FORCED_FIX_CUTOFF = "forcedFixBatteryCutoffPref";
    public static final String KEY_ADVANCED_RESET_TO_DEFAULTS = "advancedPrefs.resetToDefaults";

    public static final String KEY_SHARE_SUBJECT = "shareSubjectPref";
    public static final String KEY_SHARE_INTRO = "shareIntroPref";
    public static final String KEY_GREETINGS_RESET_TO_DEFAULTS = "greetingDefaultPrefs.resetToDefaults";

    private AppSettings m_appSettings;

    private EditTextPreference m_apiKeyPreference;
    private Preference m_testConnectionPreference;
    private Preference m_sendFeedbackPreference;

    private EditTextPreference m_locationAccuracyThresholdPreference;
    private EditTextPreference m_locationSamplePeriodPreference;
    private EditTextPreference m_locationUploadPeriodPreference;
    private CheckBoxPreference m_forcedFixEnablePreference;
    private EditTextPreference m_forcedFixPeriodPreference;
    private EditTextPreference m_forcedFixCutoffPreference;
    private Preference m_advancedResetToDefaultsPreference;

    private EditTextPreference m_shareSubjectPreference;
    private EditTextPreference m_shareIntroPreference;
    private Preference m_greetingsResetToDefaultsPreference;

    private LocationService m_boundService = null;
    private boolean m_isBound = false;

    private final ServiceConnection mConnection = new ServiceConnection() {
        @Override
        public void onServiceConnected(ComponentName className, IBinder service) {
            // This is called when the connection with the service has been
            // established, giving us the service object we can use to
            // interact with the service. Because we have bound to a explicit
            // service that we know is running in our own process, we can
            // cast its IBinder to a concrete class and directly access it.
            m_boundService = ((LocationService.LocationBinder) service).getService();
        }

        @Override
        public void onServiceDisconnected(ComponentName className) {
            // This is called when the connection with the service has been
            // unexpectedly disconnected -- that is, its process crashed.
            // Because it is running in our same process, we should never
            // see this happen.
            m_boundService = null;
        }
    };

    @Override
    @SuppressWarnings("deprecation")
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        addPreferencesFromResource(R.xml.preferences);

        m_appSettings = AppSettings.GetSettings(this);

        m_apiKeyPreference = (EditTextPreference) findPreference(KEY_API_KEY);
        m_testConnectionPreference = findPreference(KEY_TEST_CONNECTION);
        m_testConnectionPreference.setOnPreferenceClickListener(new OnPreferenceClickListener() {
            @Override
            public boolean onPreferenceClick(Preference preference) {
                new SimpleWebCommand(SettingsActivity.this, RestInterface.CheckApiKeyURL(m_appSettings), SettingsActivity.this).execute();
                return true;
            }
        });

        m_sendFeedbackPreference = findPreference(KEY_SEND_FEEDBACK);
        m_sendFeedbackPreference.setOnPreferenceClickListener(new OnPreferenceClickListener() {
            @Override
            public boolean onPreferenceClick(Preference preference) {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse("mailto:" + "Dustin@Dustinj.us"));
                PackageManager pm = getApplicationContext().getPackageManager();
                ApplicationInfo ai;

                try {
                    ai = pm.getApplicationInfo(SettingsActivity.this.getPackageName(), 0);
                } catch (final NameNotFoundException e) {
                    ai = null;
                }
                String applicationName = (String) (ai != null ? pm.getApplicationLabel(ai) : "(unknown)");

                intent.putExtra(Intent.EXTRA_SUBJECT, applicationName + " Feedback");
                intent.putExtra(Intent.EXTRA_TEXT, getResources().getText(R.string.feedback_body));
                startActivity(intent);

                return true;
            }
        });

        // Find the preferences that need updating on change
        m_locationAccuracyThresholdPreference = (EditTextPreference) findPreference(KEY_LOCATION_ACCURACY_THRESHOLD);
        m_locationSamplePeriodPreference = (EditTextPreference) findPreference(KEY_LOCATION_SAMPLE_PERIOD);
        m_locationUploadPeriodPreference = (EditTextPreference) findPreference(KEY_LOCATION_UPLOAD_PERIOD);
        m_forcedFixEnablePreference = (CheckBoxPreference) findPreference(KEY_FORCED_FIX_ENABLE);
        m_forcedFixPeriodPreference = (EditTextPreference) findPreference(KEY_FORCED_FIX_PERIOD);
        m_forcedFixCutoffPreference = (EditTextPreference) findPreference(KEY_FORCED_FIX_CUTOFF);
        m_advancedResetToDefaultsPreference = findPreference(KEY_ADVANCED_RESET_TO_DEFAULTS);
        m_shareSubjectPreference = (EditTextPreference) findPreference(KEY_SHARE_SUBJECT);
        m_shareIntroPreference = (EditTextPreference) findPreference(KEY_SHARE_INTRO);
        m_greetingsResetToDefaultsPreference = findPreference(KEY_GREETINGS_RESET_TO_DEFAULTS);

        m_advancedResetToDefaultsPreference.setOnPreferenceClickListener(new OnPreferenceClickListener() {

            @Override
            public boolean onPreferenceClick(Preference preference) {
                resetAdvancedToDefaults();
                return true;
            }

        });

        m_greetingsResetToDefaultsPreference.setOnPreferenceClickListener(new OnPreferenceClickListener() {

            @Override
            public boolean onPreferenceClick(Preference preference) {
                resetGreetingsToDefaults();
                return true;
            }

        });

        doBindService();
    }

    @Override
    protected void onResume() {
        super.onResume();

        // Setup the initial values
        updatePreferences();

        PreferenceManager.getDefaultSharedPreferences(this).registerOnSharedPreferenceChangeListener(this);
    }

    @Override
    protected void onPause() {
        super.onPause();

        PreferenceManager.getDefaultSharedPreferences(this).unregisterOnSharedPreferenceChangeListener(this);
    }

    private void doBindService() {
        bindService(new Intent(this, LocationService.class), mConnection, Context.BIND_AUTO_CREATE);
        m_isBound = true;
    }

    private void doUnbindService() {
        if (m_isBound) {
            // Detach our existing connection.
            unbindService(mConnection);
            m_isBound = false;
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        doUnbindService();
    }

    @Override
    public void onSharedPreferenceChanged(SharedPreferences sharedPreferences, String key) {
        updatePreferences();
        if (m_boundService != null) {
            Log.d(this.getClass().getSimpleName(), "Ticking service");
            m_boundService.Tick();
        }
    }

    @Override
    public void onResponseReceived(int returnCode) {
        Log.d(this.getClass().getSimpleName(), "onResponseReceived");

        if (returnCode / 100 == 2) {
            Toast.makeText(this, "Success!", Toast.LENGTH_SHORT).show();
        }
        else if (returnCode == 401) {
            Toast.makeText(this, "Invalid API Key", Toast.LENGTH_SHORT).show();
        }
        else {
            Toast.makeText(this, "Connection Error", Toast.LENGTH_SHORT).show();
        }
    }

    private void resetAdvancedToDefaults() {
        m_appSettings.ResetAdvancedSettingsToDefault();
    }

    private void resetGreetingsToDefaults() {
        m_appSettings.ResetGreetingsToDefault();
    }

    private void updatePreferences() {
        m_shareSubjectPreference.setSummary("\"" + m_appSettings.GetShareSubject() + "\"");
        m_shareIntroPreference.setSummary("\"" + m_appSettings.GetShareIntro() + "\"");

        if (m_appSettings.GetAccuracyThresholdM() == 0) {
            m_locationAccuracyThresholdPreference.setSummary("Don't filter on accuracy");
        }
        else if (m_appSettings.GetAccuracyThresholdM() == 1) {
            m_locationAccuracyThresholdPreference.setSummary("Locations must have an accuracy of at least a meter to be recorded");
        }
        else {
            m_locationAccuracyThresholdPreference.setSummary("Locations must have an accuracy of at least " + Integer.toString(m_appSettings.GetAccuracyThresholdM()) + " meters to be recorded");
        }

        if (m_appSettings.GetLocationSamplePeriodMs() == 0) {
            m_locationSamplePeriodPreference.setSummary("Don't aggregate samples, keep 'em all");
        }
        else if (m_appSettings.GetLocationSamplePeriodMs() == 1000) {
            m_locationSamplePeriodPreference.setSummary("Every second store the most accurate location available");
        }
        else {
            m_locationSamplePeriodPreference.setSummary("Every " + Integer.toString(m_appSettings.GetLocationSamplePeriodMs() / 1000) + " seconds store the most accurate location available");
        }

        if (m_appSettings.GetMinUploadPeriodMs() == 0) {
            m_locationUploadPeriodPreference.setSummary("Upload continuously");
        }
        else if (m_appSettings.GetMinUploadPeriodMs() == 60 * 1000) {
            m_locationUploadPeriodPreference.setSummary("Upload no faster than once a minute");
        }
        else {
            m_locationUploadPeriodPreference.setSummary("Upload no faster than once every " + Integer.toString(m_appSettings.GetMinUploadPeriodMs() / (60 * 1000)) + " minutes");
        }

        m_forcedFixEnablePreference.setChecked(m_appSettings.IsForcedFixEnabled());

        if (m_appSettings.IsForcedFixEnabled()) {
            if (m_appSettings.GetForcedFixPeriodMs() == 0) {
                m_forcedFixPeriodPreference.setSummary("Acquire a location fix continuously");
            }
            else if (m_appSettings.GetForcedFixPeriodMs() == 60 * 1000) {
                m_forcedFixPeriodPreference.setSummary("Acquire a location fix every minute");
            }
            else {
                m_forcedFixPeriodPreference.setSummary("Acquire a location fix at least every " + Integer.toString(m_appSettings.GetForcedFixPeriodMs() / (60 * 1000)) + " minutes");
            }

            if (m_appSettings.GetForcedFixBatteryCutoff() == 0) {
                m_forcedFixCutoffPreference.setSummary("Never disable forced fixes");
            }
            else {
                m_forcedFixCutoffPreference.setSummary("Disable forced fix when battery is lower than " + Integer.toString(m_appSettings.GetForcedFixBatteryCutoff()) + "%");
            }
        }

        m_apiKeyPreference.setSummary(m_appSettings.GetApiKey());
    }
}
