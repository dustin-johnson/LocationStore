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

public class GcmRegistrationStateMachine extends StateMachine {

    public static final int Tick = 1;
    public static final int RegistrationSuccess = 2;
    public static final int RegistrationFailure = 3;

    private final LocationService m_locationService;
    private final AppSettings.Timers m_timers;

    public static GcmRegistrationStateMachine makeStateMachine(LocationService locationService, AppSettings.Timers timers) {
        GcmRegistrationStateMachine sm = new GcmRegistrationStateMachine("GcmRegistrationStateMachine", locationService, timers);
        sm.start();
        return sm;
    }

    private void logMsg(String msg) {
        android.util.Log.d(this.getClass().getSimpleName(), msg);
    }

    GcmRegistrationStateMachine(String name, LocationService locationService, AppSettings.Timers timers) {
        super(name);
        logMsg("ctor E");

        m_locationService = locationService;
        m_timers = timers;

        // Add states, use indentation to show hierarchy
        addState(m_running);
        addState(m_connected, m_running);
        addState(m_idle, m_connected);
        addState(m_updateRegistration, m_connected);
        addState(m_notConnected, m_running);

        // Set the initial state
        setInitialState(m_notConnected);
        logMsg("ctor X");
    }

    class Running extends State {
        @Override
        public void enter() {
            logMsg(this.getClass().getSimpleName() + ".enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg(this.getClass().getSimpleName() + ".processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                break;
            default:
                break;
            }

            return HANDLED;
        }

        @Override
        public void exit() {
            logMsg(this.getClass().getSimpleName() + ".exit");
        }
    }

    class Connected extends State {
        @Override
        public void enter() {
            logMsg(this.getClass().getSimpleName() + ".enter");
            transitionTo(m_idle);
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg(this.getClass().getSimpleName() + ".processMessage what=" + message.what);

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
            logMsg(this.getClass().getSimpleName() + ".exit");
        }
    }

    class NotConnected extends State {
        @Override
        public void enter() {
            logMsg(this.getClass().getSimpleName() + ".enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg(this.getClass().getSimpleName() + ".processMessage what=" + message.what);

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
            logMsg(this.getClass().getSimpleName() + ".exit");
        }
    }

    class Idle extends State {
        @Override
        public void enter() {
            logMsg(this.getClass().getSimpleName() + ".enter");
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg(this.getClass().getSimpleName() + ".processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (!m_locationService.IsGcmRegistrationIDCommunicatedToServer()) {
                    deferMessage(message);
                    transitionTo(m_updateRegistration);
                }
                break;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg(this.getClass().getSimpleName() + ".exit");
        }
    }

    class UpdateRegistration extends State {
        @Override
        public void enter() {
            logMsg(this.getClass().getSimpleName() + ".enter");
            m_timers.GcmRegistrationRetryPeriod.SetExpired();
        }

        @Override
        public boolean processMessage(Message message) {
            logMsg(this.getClass().getSimpleName() + ".processMessage what=" + message.what);

            switch (message.what) {
            case Tick:
                if (m_timers.GcmRegistrationRetryPeriod.IsExpired()) {
                    m_timers.GcmRegistrationRetryPeriod.Restart();
                    m_locationService.SendGcmRegistrationIdUpdate();
                }
                break;
            case RegistrationSuccess:
                transitionTo(m_idle);
                return HANDLED;
            case RegistrationFailure:
                return HANDLED;
            default:
                break;
            }

            return NOT_HANDLED;
        }

        @Override
        public void exit() {
            logMsg(this.getClass().getSimpleName() + ".exit");
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
    UpdateRegistration m_updateRegistration = new UpdateRegistration();
    NotConnected m_notConnected = new NotConnected();

}
