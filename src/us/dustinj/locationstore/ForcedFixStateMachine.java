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

import us.dustinj.statemachine.State;
import us.dustinj.statemachine.StateMachine;
import android.os.Message;

public class ForcedFixStateMachine extends StateMachine {

    public static final int Tick = 1;
    public static final int ReceivedLocation = 2;
    public static final int ReceivedGPSFix = 3;
    public static final int ForceOneTimeFix = 4;

    private final LocationService m_locationService;
    private final AppSettings m_appSettings;
    private final TickManager m_tickManager;

    public static ForcedFixStateMachine makeStateMachine(LocationService locationService, AppSettings appSettings) {
        logMsg("makeStateMachine E");
        ForcedFixStateMachine sm = new ForcedFixStateMachine("ForcedFixStateMachine", locationService, appSettings);
        sm.start();
        logMsg("makeStateMachine X");
        return sm;
    }

    private static void logMsg(String msg) {
        // android.util.Log.d("ForcedFixStateMachine", msg);
    }

    ForcedFixStateMachine(String name, LocationService locationService, AppSettings appSettings) {
        super(name);
        logMsg("ctor E");

        m_locationService = locationService;
        m_appSettings = appSettings;
        m_tickManager = TickManager.GetTickManager(m_locationService);

        // Add states, use indentation to show hierarchy
        addState(m_running);
        addState(m_idle, m_running);
        addState(m_acquiringFix, m_running);
        addState(m_acquiringFix_Automatic, m_acquiringFix);
        addState(m_settlingFix, m_running);
        addState(m_settlingFix_Automatic, m_settlingFix);

        // Set the initial state
        setInitialState(m_idle);
        logMsg("ctor X");
    }

    class Running extends State {
        @Override
        public void enter() {
            logMsg("Running.enter");

            m_appSettings.GetTimers().ForcedFixPeriod.Restart();
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("Running.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                break;
            case ReceivedLocation:
                m_appSettings.GetTimers().ForcedFixPeriod.Restart();
                break;
            default:
                break;
            }

            return HANDLED;
        }

        @Override
        public void exit() {
            logMsg("Running.exit");
        }
    }

    class Idle extends State {
        @Override
        public void enter() {
            logMsg("Idle.enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("Idle.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (m_appSettings.IsForcedFixEnabled()) {
                    if (m_appSettings.GetTimers().ForcedFixPeriod.IsExpired()) {
                        if (m_locationService.IsBatteryAboveThreshold()) {
                            if (m_locationService.TurnOnGPS()) {
                                transitionTo(m_acquiringFix_Automatic);
                                return HANDLED;
                            }
                        }
                    }
                }
                break;
            case ForceOneTimeFix:
                if (m_locationService.TurnOnGPS()) {
                    transitionTo(m_acquiringFix);
                    return HANDLED;
                }
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("Idle.exit");
            m_appSettings.GetTimers().ForcedFixPeriod.Restart();
        }
    }

    class AcquiringFix extends State {
        @Override
        public void enter() {
            logMsg("AcquiringFix.enter");
            m_appSettings.GetTimers().GpsFixAcquirePeriod.Restart();
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("AcquiringFix.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (m_appSettings.GetTimers().GpsFixAcquirePeriod.IsExpired()) {
                    m_locationService.TurnOffGPS();
                    transitionTo(m_idle);
                    return HANDLED;
                }
                break;
            case ReceivedGPSFix:
                transitionTo(m_settlingFix);
                return HANDLED;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("AcquiringFix.exit");
        }
    }

    class AcquiringFix_Automatic extends State {
        @Override
        public void enter() {
            logMsg("AcquiringFix_Automatic.enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("AcquiringFix_Automatic.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (!m_appSettings.IsForcedFixEnabled() || !m_locationService.IsBatteryAboveThreshold()) {
                    m_locationService.TurnOffGPS();
                    transitionTo(m_idle);
                    return HANDLED;
                }
                break;
            case ReceivedGPSFix:
                transitionTo(m_settlingFix_Automatic);
                return HANDLED;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("AcquiringFix_Automatic.exit");
        }
    }

    class SettlingFix extends State {
        @Override
        public void enter() {
            logMsg("SettlingFix.enter");
            m_appSettings.GetTimers().GpsFixSettlePeriod.Restart();
            m_tickManager.AddRegistration(this, m_appSettings.GetTimers().GpsFixSettlePeriod.GetDuration()); // Make sure we get ticked enough to turn off the GPS
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("SettlingFix.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (m_appSettings.GetTimers().GpsFixSettlePeriod.IsExpired()) {
                    transitionTo(m_idle);
                    return HANDLED;
                }
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("SettlingFix.exit");
            m_locationService.TurnOffGPS();
            m_tickManager.RemoveRegistration(this);
            m_locationService.FlushLocationAggregator();
        }
    }

    // Used for when the fix was force by automatic means, not explicit user request.
    class SettlingFix_Automatic extends State {
        @Override
        public void enter() {
            logMsg("SettlingFix_Automatic.enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("SettlingFix_Automatic.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (m_appSettings.GetTimers().ForcedFixPeriod.IsExpired()) {
                    return HANDLED;
                } else if (!m_locationService.IsBatteryAboveThreshold() || !m_appSettings.IsForcedFixEnabled()) {
                    transitionTo(m_idle);
                    return HANDLED;
                }
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("SettlingFix_Automatic.exit");
        }
    }

    @Override
    protected void onHalting() {
        logMsg("halting");
        synchronized (this) {
            this.notifyAll();
        }
    }

    Running m_running = new Running();
    Idle m_idle = new Idle();
    AcquiringFix m_acquiringFix = new AcquiringFix();
    AcquiringFix_Automatic m_acquiringFix_Automatic = new AcquiringFix_Automatic();
    SettlingFix m_settlingFix = new SettlingFix();
    SettlingFix_Automatic m_settlingFix_Automatic = new SettlingFix_Automatic();

}
