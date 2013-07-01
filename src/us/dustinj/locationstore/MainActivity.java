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

import java.util.ArrayList;
import java.util.Iterator;

import us.dustinj.locationstore.ExportArrayAdapter.ExportsChangedListener;
import us.dustinj.locationstore.LocationService.LocationBinder;
import us.dustinj.locationstore.TimeDurationDialog.TimeDurationDialogListener;
import us.dustinj.locationstore.io.ExportRetriever;
import us.dustinj.locationstore.io.ExportRetriever.ExportResponseHandler;
import us.dustinj.locationstore.io.HttpStatusResponseHandler;
import us.dustinj.locationstore.io.RestInterface;
import us.dustinj.locationstore.io.SimpleWebCommand;
import android.app.Activity;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.ServiceConnection;
import android.os.Bundle;
import android.os.IBinder;
import android.os.Parcelable;
import android.util.Log;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.ListView;
import android.widget.Toast;

public class MainActivity extends Activity implements ExportResponseHandler, TimeDurationDialogListener, HttpStatusResponseHandler, ExportsChangedListener {
    private AppSettings m_appSettings;
    private ListView m_listView;
    ArrayList<Export> m_exports = new ArrayList<Export>();

    LocationService m_service = null;
    boolean m_boundToService = false;

    /** Defines callback for service binding, passed to bindService() */
    private final ServiceConnection m_serviceConnection = new ServiceConnection() {

        @Override
        public void onServiceConnected(ComponentName className,
                IBinder service) {
            // We've bound to LocalService, cast the IBinder and get LocalService instance
            LocationBinder binder = (LocationBinder) service;
            m_service = binder.getService();
            m_boundToService = true;
        }

        @Override
        public void onServiceDisconnected(ComponentName arg0) {
            m_boundToService = false;
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        m_appSettings = AppSettings.GetSettings(this);
        m_listView = (ListView) findViewById(R.id.exportsListView);

        if (savedInstanceState != null && savedInstanceState.containsKey("m_exports")) {
            ArrayList<Parcelable> parcels = savedInstanceState.getParcelableArrayList("m_exports");
            for (Iterator<Parcelable> it = parcels.iterator(); it.hasNext();) {
                m_exports.add(0, (Export) it.next());
            }
        }
        setExports(m_exports);
    }

    @Override
    public void onResume() {
        super.onResume();

        onExportsChanged();
    }

    @Override
    protected void onStart() {
        super.onStart();

        Intent intent = new Intent(this, LocationService.class);
        bindService(intent, m_serviceConnection, Context.BIND_AUTO_CREATE);
    }

    @Override
    protected void onStop() {
        super.onStop();

        if (m_boundToService) {
            unbindService(m_serviceConnection);
        }
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.activity_main, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {

        switch (item.getItemId()) {
        case R.id.menu_settings: {
            Intent settingsActivity = new Intent(getBaseContext(), SettingsActivity.class);
            startActivity(settingsActivity);
        }
            return true;

        case R.id.menu_refresh: {
            onExportsChanged();
        }
            return true;
        default:
            return super.onOptionsItemSelected(item);
        }
    }

    public void onCreateExportClick(View src) {
        TimeDurationDialog timeDialog = new TimeDurationDialog();
        timeDialog.show(getFragmentManager(), "TimeDurationDialog");
    }

    public void onRefreshLocationClick(View src) {
        if (m_boundToService) {
            m_service.ForceLocationFix();
        }
    }

    public void onDebugButtonClick(View src) {
        if (m_boundToService) {
            // m_service.SetGcmRegistrationID(android.text.format.DateFormat.format("h-mm-s-aa", new Date()).toString());
            m_service
                    .SetGcmRegistrationID("APA91bH6qnHsc5I-q3hQEtc67NmopJD2KH2O60P1wQuHsELhgxUuK-E0TzSLM1FyqIktN4Qjm5h2po5o6xwKaZTFIHvtGuF1BZBw5kTLLg3WS5DbyRgDHNaBaykMQYDormBZvU1hdh1ZDT56eJhh3RgeLePPFafsXwYkrkAxBkcLhhTCgl3eLUw");
        }
    }

    @Override
    public void onExportsReceived(int returnCode, ArrayList<Export> exports) {
        setExports(exports);
    }

    @Override
    public void onSaveInstanceState(Bundle saveBundle) {
        if (m_exports != null) {
            saveBundle.putParcelableArrayList("m_exports", m_exports);
        }
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(this.getClass().getSimpleName(), "onDestroy");
        m_appSettings.Close();
    }

    private void setExports(ArrayList<Export> exports) {
        m_exports = exports;
        ExportArrayAdapter adapter = new ExportArrayAdapter(this, m_listView, m_exports.toArray(new Export[0]), this);

        // Assign adapter to ListView
        m_listView.setAdapter(adapter);
    }

    @Override
    public void onTimeConfirmed(boolean forever, int days, int hours, int minutes) {
        long durationMs = 0;

        if (forever) {
            durationMs = -1;
        } else {
            durationMs = ((((days * 24) + hours) * 60) + minutes) * 60 * 1000;
        }

        SimpleWebCommand exportCreator = new SimpleWebCommand(this, RestInterface.CreateExportID(m_appSettings, durationMs), this);
        exportCreator.execute();
    }

    @Override
    public void onResponseReceived(int returnCode) {
        if (returnCode == 200) {
            Toast.makeText(MainActivity.this, "Export Created", Toast.LENGTH_SHORT).show();
        } else {
            Toast.makeText(MainActivity.this, "Export creation failed", Toast.LENGTH_SHORT).show();
        }
        onExportsChanged();

        if (m_boundToService) {
            m_service.ForceLocationFix();
        }
    }

    @Override
    public void onExportsChanged() {
        new ExportRetriever(this, this).execute();
    }
}
