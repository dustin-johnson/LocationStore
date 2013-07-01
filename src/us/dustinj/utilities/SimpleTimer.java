package us.dustinj.utilities;

import java.util.Calendar;

public class SimpleTimer {
    private long m_graceTimeMs;
    private long m_markerTime;
    private long m_durationMs;

    public SimpleTimer(long durationMs) {
        SetDuration(durationMs);
        SetGraceTimeMs(0);
        Restart();
    }

    public SimpleTimer(long durationMs, boolean startExpired) {
        SetDuration(durationMs);
        SetGraceTimeMs(0);

        if (startExpired) {
            SetExpired();
        }
        else {
            Restart();
        }
    }

    public boolean IsExpired() {
        // Log.d(this.getClass().getSimpleName(), "IsExpired: markerTime - " + m_markerTime + " currentTime - " + getCurrentMs());
        long currentTime = getCurrentMs();
        boolean expired = ((m_markerTime + m_durationMs) < (currentTime + m_graceTimeMs));
        return expired;
    }

    public void Restart() {
        m_markerTime = getCurrentMs();
    }

    public void SetDuration(long durationMs) {
        m_durationMs = durationMs;
    }

    public long GetDuration() {
        return m_durationMs;
    }

    public void SetExpired() {
        m_markerTime = Long.MIN_VALUE;
    }

    public void SetGraceTimeMs(long graceTimeMs) {
        m_graceTimeMs = graceTimeMs;
    }

    private long getCurrentMs() {
        return Calendar.getInstance().getTimeInMillis();
    }
}
