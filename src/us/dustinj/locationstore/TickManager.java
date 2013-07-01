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

import java.util.Iterator;
import java.util.LinkedList;

import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.SystemClock;
import android.util.Log;

public class TickManager {
    private static TickManager m_manager = null;

    private final Context m_context;
    private final LinkedList<TickRegistration> m_registrations;

    private class TickRegistration {
        public Object Requester;
        public long TickIntervalMs;

        public TickRegistration(Object requester, long tickIntervalMs) {
            Requester = requester;
            TickIntervalMs = tickIntervalMs;
        }
    }

    public static TickManager GetTickManager(Context context) {
        if (m_manager == null) {
            synchronized (TickManager.class) {
                if (m_manager == null) {
                    m_manager = new TickManager(context);
                }
            }
        }

        return m_manager;
    }

    private TickManager(Context context) {
        m_context = context;
        m_registrations = new LinkedList<TickRegistration>();
    }

    public void AddRegistration(Object requester, long tickIntervalMs) {
        synchronized (this) {
            // Search through the registration list to make sure we don't already have a registration for the requester
            for (Iterator<TickRegistration> it = m_registrations.iterator(); it.hasNext();) {
                TickRegistration current = it.next();

                if (current.Requester == requester) {
                    current.TickIntervalMs = tickIntervalMs;
                    updateTickRegistration();
                    return;
                }
            }

            // If not, add it
            m_registrations.add(new TickRegistration(requester, tickIntervalMs));
            updateTickRegistration();
        }
    }

    public void RemoveRegistration(Object requester) {
        synchronized (this) {
            for (Iterator<TickRegistration> it = m_registrations.iterator(); it.hasNext();) {
                TickRegistration current = it.next();

                if (current.Requester == requester) {
                    m_registrations.remove(current);
                    updateTickRegistration();
                    break;
                }
            }
        }
    }

    private void updateTickRegistration() {
        synchronized (this) {
            PendingIntent locationPendingIntent = PendingIntent.getService(m_context, 0, new Intent(m_context, LocationService.class), 0);
            AlarmManager manager = (AlarmManager) m_context.getSystemService(Context.ALARM_SERVICE);

            if (m_registrations.size() == 0) {
                Log.d(this.getClass().getSimpleName(), "updateTickRegistration: Removing system callback as all tick registrations are gone");
                manager.cancel(locationPendingIntent);
            } else {
                long systemCallbackTimeMs = getSystemCallbackTimeMs();

                Log.d(this.getClass().getSimpleName(), "updateTickRegistration: Setting system callback to " + systemCallbackTimeMs + "ms");
                manager.setRepeating(AlarmManager.ELAPSED_REALTIME_WAKEUP, SystemClock.elapsedRealtime(), systemCallbackTimeMs, locationPendingIntent);
            }
        }
    }

    private long getSystemCallbackTimeMs()
    {
        long result = m_registrations.peekFirst().TickIntervalMs;

        for (Iterator<TickRegistration> it = m_registrations.iterator(); it.hasNext();) {
            TickRegistration current = it.next();

            result = greatestCommonDivisor(result, current.TickIntervalMs);
        }

        return result;
    }

    private static long greatestCommonDivisor(long a, long b)
    {
        while (b > 0)
        {
            long temp = b;

            b = a % b;
            a = temp;
        }

        return a;
    }
}
