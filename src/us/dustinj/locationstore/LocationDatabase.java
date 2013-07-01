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

import java.util.HashMap;
import java.util.Map.Entry;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.util.Log;

public class LocationDatabase extends SQLiteOpenHelper {

    private static final String DATABASE_NAME = "Locations";
    private static final String TABLE_NAME = "locations";
    private static final int DATABASE_VERSION = 4;

    private final SQLiteDatabase m_database;
    private static HashMap<String, DatabaseField> m_columnDefintions;
    private static String[] m_readColumns;

    public LocationDatabase(Context context) {
        super(context, DATABASE_NAME, null, DATABASE_VERSION);
        m_database = getWritableDatabase();
    }

    public long CreateRecord(ExtendedLocation loc) {
        ContentValues values = new ContentValues();

        values.put("locationTimestamp", loc.getTime());
        values.put("lat_deg", loc.getLatitude());
        values.put("lon_deg", loc.getLongitude());

        if (loc.hasAltitude()) {
            values.put("alt_m", Math.round(loc.getAltitude()));
        }

        if (loc.hasAccuracy()) {
            values.put("accuracy_m", Math.round(loc.getAccuracy()));
            Log.d(this.getClass().getSimpleName(), "Location accuracy: " + Math.round(loc.getAccuracy()));
        }

        if (loc.hasSpeed()) {
            values.put("speed_mps", loc.getSpeed());
        }

        if (loc.hasBearing()) {
            values.put("bearing_deg", Math.round(loc.getBearing()));
        }

        if (loc.getProvider() != null) {
            values.put("provider", loc.getProvider());
            Log.d(this.getClass().getSimpleName(), "Location provider: " + loc.getProvider());
        }

        if (loc.hasBatteryPercent()) {
            values.put("battery_percent", Math.round(loc.getBatteryPercent()));
            Log.d(this.getClass().getSimpleName(), "Battery percent: " + Math.round(loc.getBatteryPercent()));
        }

        return m_database.insert(TABLE_NAME, null, values);
    }

    public int DeleteRecords(long maxIdInclusive) {
        String selectionArray[] = { Long.toString(maxIdInclusive) };

        return m_database.delete(TABLE_NAME, "_id <= ?", selectionArray);
    }

    public Cursor GetRecordsUpToID(long maxIdInclusive) {
        if (m_readColumns == null)
        {
            m_readColumns = new String[GetColumnDefinitions().keySet().size()];
            m_readColumns = GetColumnDefinitions().keySet().toArray(m_readColumns);
        }

        String selectionArray[] = { Long.toString(maxIdInclusive) };
        Cursor cursor = m_database.query(true, TABLE_NAME, m_readColumns, "_id <= ?", selectionArray, null, null, null, null);

        if (cursor != null) {
            cursor.moveToFirst();
        }

        return cursor;
    }

    public long GetCurrentID() {
        long maxID = 0;
        Cursor cursor = m_database.rawQuery("SELECT MAX(_id) FROM " + TABLE_NAME, null);

        if (cursor != null) {
            cursor.moveToFirst();
            maxID = cursor.getLong(0);
        }

        Log.d(this.getClass().getSimpleName(), "GetCurrentID: " + Long.valueOf(maxID));
        return maxID;
    }

    public long GetRecordCount() {
        // return DatabaseUtils.queryNumEntries(m_database, TABLE_NAME, "", null);

        Cursor cursor = m_database.rawQuery("SELECT count(*) FROM " + TABLE_NAME, null);
        cursor.moveToFirst();
        int count = cursor.getInt(0);
        cursor.close();

        return count;
    }

    public static HashMap<String, DatabaseField> GetColumnDefinitions() {
        if (m_columnDefintions == null) {
            int index = 1;
            m_columnDefintions = new HashMap<String, DatabaseField>();
            DatabaseField fieldDefinitions[] = {
                    new DatabaseField("locationTimestamp", "TEXT", true, index++),
                    new DatabaseField("lat_deg", "REAL", true, index++),
                    new DatabaseField("lon_deg", "REAL", true, index++),
                    new DatabaseField("alt_m", "INTEGER", false, index++),
                    new DatabaseField("accuracy_m", "INTEGER", false, index++),
                    new DatabaseField("speed_mps", "REAL", false, index++),
                    new DatabaseField("bearing_deg", "INTEGER", false, index++),
                    new DatabaseField("provider", "TEXT", false, index++),
                    new DatabaseField("battery_percent", "INTEGER", false, index++)
            };

            // Spool the temporary array into a hashmap so we can quickly access the fieldDefinitions when needed
            for (DatabaseField field : fieldDefinitions) {
                m_columnDefintions.put(field.Name, field);
            }

        }

        return m_columnDefintions;
    }

    // Method is called during creation of the database
    @Override
    public void onCreate(SQLiteDatabase database) {
        HashMap<String, DatabaseField> columns = GetColumnDefinitions();
        StringBuilder sql = new StringBuilder();
        StringBuilder unique = new StringBuilder();

        // Produce something like
        // "CREATE TABLE locations(_id INTEGER PRIMARY KEY ASC, locationTimestamp TEXT, lat_rad REAL, lon_rad REAL, alt_m REAL, " +
        // "accuracy_m REAL, speed_mps REAL, bearing_rad REAL, provider TEXT, UNIQUE (locationTimestamp, deviceID, lat_rad, lon_rad));"

        sql.append("CREATE TABLE ");
        sql.append(TABLE_NAME);
        sql.append("(_id INTEGER PRIMARY KEY ASC, ");

        for (Entry<String, DatabaseField> entry : columns.entrySet()) {
            sql.append(entry.getKey());
            sql.append(" \"");
            sql.append(entry.getValue().Type);
            sql.append("\", ");

            if (entry.getValue().IsUnique) {
                if (unique.length() != 0) {
                    unique.append(", ");
                }
                unique.append(entry.getKey());
            }
        }

        sql.append("UNIQUE (");
        sql.append(unique);
        sql.append("));");

        Log.w(this.getClass().getName(), "Creating databse with: '" + sql.toString() + "'");
        database.execSQL(sql.toString());
    }

    // Method is called during an upgrade of the database,
    @Override
    public void onUpgrade(SQLiteDatabase database, int oldVersion, int newVersion) {
        Log.w(this.getClass().getName(), "Upgrading database from version " + oldVersion + " to " + newVersion + ", which will destroy all old data");
        database.execSQL("DROP TABLE IF EXISTS " + TABLE_NAME);
        onCreate(database);
    }

}
