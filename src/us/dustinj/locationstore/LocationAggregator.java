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

import java.util.Calendar;
import java.util.Comparator;
import java.util.TreeSet;

import android.location.Location;

public class LocationAggregator {

    private final TreeSet<ExtendedLocation> locations = new TreeSet<ExtendedLocation>(new LocationTimeComparator());
    private final AppSettings m_appSettings;
    private final LocationDatabase m_database;

    LocationAggregator(AppSettings appSettings, LocationDatabase database) {
        m_appSettings = appSettings;
        m_database = database;
    }

    public void InsertLocation(ExtendedLocation loc) {
        locations.add(loc);
        Tick();
    }

    public void Tick() {
        int period = m_appSettings.GetLocationSamplePeriodMs();
        long currentTime = Calendar.getInstance().getTimeInMillis();

        // 1 - While we have something to export
        while (!locations.isEmpty() && (locations.first().getTime() + period) <= currentTime) {
            long startOfPeriod = locations.first().getTime();
            ExtendedLocation bestLocation = locations.first();

            // 2 - Chop it up into period sized chunks and pick the best one
            while (!locations.isEmpty() && locations.first().getTime() <= (startOfPeriod + period)) {
                ExtendedLocation currentLocation = locations.first();
                locations.remove(currentLocation);

                if (currentLocation.hasAccuracy() && currentLocation.getAccuracy() <= bestLocation.getAccuracy()) {
                    bestLocation = currentLocation;
                }
            }

            // 3 - Insert just the best one into the database
            m_database.CreateRecord(bestLocation);
        }
    }

    public void Flush() {
        Tick(); // Dispatch all of the whole chunks

        // 1 - If we have something to export
        if (!locations.isEmpty()) {
            ExtendedLocation bestLocation = locations.first();

            // 2 - Pick the best one
            while (!locations.isEmpty()) {
                ExtendedLocation currentLocation = locations.first();
                locations.remove(currentLocation);

                if (currentLocation.hasAccuracy() && currentLocation.getAccuracy() <= bestLocation.getAccuracy()) {
                    bestLocation = currentLocation;
                }
            }

            // 3 - Insert just the best one into the database
            m_database.CreateRecord(bestLocation);
        }
    }

    class LocationTimeComparator implements Comparator<Location>
    {
        @Override
        public int compare(Location loc1, Location loc2)
        {
            if (loc1.getTime() == loc2.getTime())
            {
                return 0;
            }
            else if (loc1.getTime() > loc2.getTime())
            {
                return 1;
            }
            else
            {
                return -1;
            }
        }
    }
}
