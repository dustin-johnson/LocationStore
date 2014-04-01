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

public class ExportStateMachine extends StateMachine {

    public static final int Tick = 1;
    public static final int ExportSuccess = 2;
    public static final int ExportFailure = 3;
    public static final int ForceQuickUpload = 4;

    private final LocationService m_locationService;
    private final AppSettings.Timers m_timers;

    public static ExportStateMachine makeStateMachine(LocationService locationService, AppSettings.Timers timers) {
        logMsg("makeStateMachine E");
        ExportStateMachine sm = new ExportStateMachine("ExportStateMachine", locationService, timers);
        sm.start();
        logMsg("makeStateMachine X");
        return sm;
    }

    private static void logMsg(String msg) {
        android.util.Log.d("ExportStateMachine", msg);
    }

    ExportStateMachine(String name, LocationService locationService, AppSettings.Timers timers) {
        super(name);
        logMsg("ctor E");

        m_locationService = locationService;
        m_timers = timers;

        addState(m_running);
        addState(m_connected, m_running);
        addState(m_idle, m_connected);
        addState(m_exporting, m_connected);
        addState(m_notConnected, m_running);

        // Set the initial state
        setInitialState(m_notConnected);
        logMsg("ctor X");
    }

    class Running extends State {
        @Override
        public void enter() {
            logMsg("Running.enter");

            m_timers.ExportPeriod.Restart();
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("Running.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                break;
            case ForceQuickUpload:
                m_timers.ExportPeriod.SetExpired();
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

    class Connected extends State {
        @Override
        public void enter() {
            logMsg("Connected.enter");
            transitionTo(m_idle);
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("Connected.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (!m_locationService.IsNetworkConnected()) {
                    deferMessage(message);
                    transitionTo(m_notConnected);
                }
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("Connected.exit");
        }
    }

    class NotConnected extends State {
        @Override
        public void enter() {
            logMsg("NotConnected.enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("NotConnected.processMessage what=" + message.what);

            switch (message.what) {
            case Tick: {
                if (m_locationService.IsNetworkConnected()) {
                    deferMessage(message);
                    transitionTo(m_connected);
                }
            }
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("NotConnected.exit");
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
                if (m_timers.ExportPeriod.IsExpired() && m_locationService.ExportLocations()) {
                    m_timers.ExportPeriod.Restart();
                    transitionTo(m_exporting);
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
        }
    }

    class Exporting extends State {
        @Override
        public void enter() {
            logMsg("Exporting.enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg("Exporting.processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                break;
            case ExportSuccess:
            case ExportFailure:
                transitionTo(m_idle);
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg("Exporting.exit");
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
    Connected m_connected = new Connected();
    Idle m_idle = new Idle();
    Exporting m_exporting = new Exporting();
    NotConnected m_notConnected = new NotConnected();

}
