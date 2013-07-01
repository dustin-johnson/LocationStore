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

import android.app.Activity;
import android.app.AlertDialog;
import android.app.Dialog;
import android.app.DialogFragment;
import android.content.DialogInterface;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.CheckBox;
import android.widget.CompoundButton;
import android.widget.CompoundButton.OnCheckedChangeListener;
import android.widget.NumberPicker;
import android.widget.TextView;

public class TimeDurationDialog extends DialogFragment {

    public interface TimeDurationDialogListener {
        public void onTimeConfirmed(boolean forever, int days, int hours, int minutes);
    }

    private TimeDurationDialogListener m_listener;

    private NumberPicker m_daysPicker;
    private NumberPicker m_hoursPicker;
    private NumberPicker m_minutesPicker;

    TextView m_daysLabel;
    TextView m_hoursLabel;
    TextView m_minutesLabel;

    private CheckBox m_foreverCheckBox;

    @Override
    public void onAttach(Activity activity) {
        super.onAttach(activity);

        try {
            m_listener = (TimeDurationDialogListener) activity;
        } catch (ClassCastException e) {
            throw new ClassCastException(activity.toString() + " must implement NoticeDialogListener");
        }
    }

    @Override
    public Dialog onCreateDialog(Bundle savedInstanceState) {
        AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
        // Get the layout inflater
        LayoutInflater inflater = getActivity().getLayoutInflater();

        View dialogView = inflater.inflate(R.layout.time_duration_dialog_layout, null);
        m_daysPicker = (NumberPicker) dialogView.findViewById(R.id.days_picker);
        m_hoursPicker = (NumberPicker) dialogView.findViewById(R.id.hours_picker);
        m_minutesPicker = (NumberPicker) dialogView.findViewById(R.id.minutes_picker);

        m_daysLabel = (TextView) dialogView.findViewById(R.id.days_label);
        m_hoursLabel = (TextView) dialogView.findViewById(R.id.hours_label);
        m_minutesLabel = (TextView) dialogView.findViewById(R.id.minutes_label);

        m_foreverCheckBox = (CheckBox) dialogView.findViewById(R.id.forever_checkBox);

        m_daysPicker.setMinValue(0);
        m_daysPicker.setMaxValue(364);
        m_daysPicker.setValue(0);
        m_daysPicker.setWrapSelectorWheel(false);

        m_hoursPicker.setMinValue(0);
        m_hoursPicker.setMaxValue(23);
        m_hoursPicker.setValue(2);
        m_hoursPicker.setWrapSelectorWheel(false);

        String[] minutesArray = new String[] { "0", "10", "20", "30", "40", "50" };
        m_minutesPicker.setMinValue(0);
        m_minutesPicker.setMaxValue(5);
        m_minutesPicker.setValue(0);
        m_minutesPicker.setDisplayedValues(minutesArray);
        m_minutesPicker.setWrapSelectorWheel(false);

        m_foreverCheckBox.setOnCheckedChangeListener(new OnCheckedChangeListener() {
            @Override
            public void onCheckedChanged(CompoundButton buttonView, boolean isChecked) {
                m_daysPicker.setEnabled(!isChecked);
                m_hoursPicker.setEnabled(!isChecked);
                m_minutesPicker.setEnabled(!isChecked);

                m_daysLabel.setEnabled(!isChecked);
                m_hoursLabel.setEnabled(!isChecked);
                m_minutesLabel.setEnabled(!isChecked);
            }
        });

        // Inflate and set the layout for the dialog
        // Pass null as the parent view because its going in the dialog layout
        builder.setView(dialogView)
                // Add action buttons
                .setPositiveButton("Create", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int id) {
                        m_listener.onTimeConfirmed(TimeDurationDialog.this.m_foreverCheckBox.isChecked(),
                                TimeDurationDialog.this.m_daysPicker.getValue(),
                                TimeDurationDialog.this.m_hoursPicker.getValue(),
                                TimeDurationDialog.this.m_minutesPicker.getValue() * 10);
                    }
                })
                .setNegativeButton("Cancel", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int id) {
                        TimeDurationDialog.this.getDialog().cancel();
                    }
                });
        builder.setTitle("Export Duration");

        return builder.create();
    }
}
