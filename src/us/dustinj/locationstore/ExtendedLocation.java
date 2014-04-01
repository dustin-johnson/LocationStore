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

import android.location.Location;

/**
 * Extends the {@code Location} class to allow the convenient transfer of metadata surrounding the location.
 *
 */
public class ExtendedLocation extends Location {

    private boolean m_hasBatteryPercent = false;
    private float m_batteryPercent = 0;

    public ExtendedLocation(String provider) {
        super(provider);
    }

    public ExtendedLocation(Location l) {
        super(l);
    }

    public ExtendedLocation(ExtendedLocation l) {
        super(l);
        set(l);
    }

    public void set(ExtendedLocation l) {
        super.set(l);
        m_hasBatteryPercent = l.m_hasBatteryPercent;
        m_batteryPercent = l.m_batteryPercent;
    }

    @Override
    public void reset() {
        super.reset();

        m_hasBatteryPercent = false;
        m_batteryPercent = 0;
    }

    public boolean hasBatteryPercent() {
        return m_hasBatteryPercent;
    }

    public float getBatteryPercent() {
        return m_batteryPercent;
    }

    public void setBatteryPercent(float percent) {
        m_hasBatteryPercent = true;
        m_batteryPercent = percent;
    }

}
