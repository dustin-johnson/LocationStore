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

import java.util.Date;

import org.json.JSONException;
import org.json.JSONObject;

import android.os.Parcel;
import android.os.Parcelable;

public class Export implements Parcelable {
    private long m_startTimeMs = 0;
    private long m_durationMs = 0;
    private String m_exportID = "unset";

    public Export(JSONObject jsonObject) {

        try {
            if (jsonObject.has("startTimestamp")) {
                SetStartTime(jsonObject.getLong("startTimestamp"));
            }
        } catch (JSONException ex) {
        }

        try {
            if (jsonObject.has("durationMs")) {
                SetDuration(jsonObject.getLong("durationMs"));
            }
        } catch (JSONException ex) {
        }

        try {
            if (jsonObject.has("exportID")) {
                SetExportID(jsonObject.getString("exportID"));
            }
        } catch (JSONException ex) {
        }
    }

    public Export(long startTimeMs, long durationMs, String exportID) {
        SetStartTime(startTimeMs);
        SetDuration(startTimeMs);
        SetExportID(exportID);
    }

    public Export(Parcel in) {
        SetStartTime(in.readLong());
        SetDuration(in.readLong());
        SetExportID(in.readString());
    }

    public void SetStartTime(long startTimeMs) {
        m_startTimeMs = startTimeMs;
    }

    public Date GetStartTime() {
        return new Date(m_startTimeMs);
    }

    public void SetDuration(long durationMs) {
        m_durationMs = durationMs;
    }

    public long GetDurationMs() {
        return m_durationMs;
    }

    public Date GetEndTime() {
        return new Date(m_startTimeMs + m_durationMs);
    }

    public void SetExportID(String exportID) {
        m_exportID = exportID;
    }

    public String GetExportID() {
        return m_exportID;
    }

    @Override
    public void writeToParcel(Parcel dest, int flags) {
        dest.writeLong(GetStartTime().getTime());
        dest.writeLong(GetDurationMs());
        dest.writeString(GetExportID());
    }

    public static final Parcelable.Creator<Export> CREATOR = new Parcelable.Creator<Export>() {
        @Override
        public Export createFromParcel(Parcel in) {
            return new Export(in);
        }

        @Override
        public Export[] newArray(int size) {
            return new Export[size];
        }
    };

    @Override
    public int describeContents() {
        return 0;
    }
}
