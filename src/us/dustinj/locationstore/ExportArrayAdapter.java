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
import java.util.Date;

import us.dustinj.locationstore.io.HttpStatusResponseHandler;
import us.dustinj.locationstore.io.RestInterface;
import us.dustinj.locationstore.io.SimpleWebCommand;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.view.LayoutInflater;
import android.view.View;
import android.view.View.OnClickListener;
import android.view.ViewGroup;
import android.widget.ArrayAdapter;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;

/**
 * Adapts a standard ArrayAdapter to be specific for handling Export models. This class connects the action
 * buttons for an export in the list (delete, copy, share, edit, etc.) to their intended functionality.
 *
 */
public class ExportArrayAdapter extends ArrayAdapter<Export> {
    private final Context m_context;
    private final Export[] m_exports;
    private final ListView m_listView;
    private final ExportsChangedListener m_exportsChangedListener;
    private final AppSettings m_appPrefs;

    public interface ExportsChangedListener {
        public void onExportsChanged();
    }

    public ExportArrayAdapter(Context context, ListView listView, Export[] exports, ExportsChangedListener exportsChangedListener) {
        super(context, R.layout.export_row_layout, exports);

        m_context = context;
        m_listView = listView;
        m_exports = exports;
        m_exportsChangedListener = exportsChangedListener;
        m_appPrefs = AppSettings.GetSettings(context);
    }

    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        View rowView = convertView;

        if (rowView == null) {
            LayoutInflater inflator = (LayoutInflater) m_context.getSystemService(Context.LAYOUT_INFLATER_SERVICE);
            rowView = inflator.inflate(R.layout.export_row_layout, parent, false);
        }

        Export currentExport = m_exports[position];
        TextView startText = (TextView) rowView.findViewById(R.id.startTimeText);
        TextView endText = (TextView) rowView.findViewById(R.id.endTimeText);
        View actionLayout = rowView.findViewById(R.id.actionLayout);

        View copyButton = rowView.findViewById(R.id.copyButton);
        View editButton = rowView.findViewById(R.id.editButton);
        View viewButton = rowView.findViewById(R.id.viewButton);
        View shareButton = rowView.findViewById(R.id.shareButton);
        View deleteButton = rowView.findViewById(R.id.deleteButton);

        rowView.setTag(currentExport);
        copyButton.setTag(currentExport);
        editButton.setTag(currentExport);
        viewButton.setTag(currentExport);
        shareButton.setTag(currentExport);
        deleteButton.setTag(currentExport);

        actionLayout.setVisibility(View.GONE);

        rowView.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View arg0) {
                View actionLayout = arg0.findViewById(R.id.actionLayout);

                if (actionLayout.getVisibility() == View.GONE) {
                    // Search through the list of rows and close any outstanding actionLayouts if they are open
                    for (int i = 0; i < m_listView.getChildCount(); i++) {
                        View currentView = m_listView.getChildAt(i);

                        if (currentView != arg0) {
                            View otherActionLayout = currentView.findViewById(R.id.actionLayout);

                            if (otherActionLayout != null) {
                                otherActionLayout.setVisibility(View.GONE);
                            }
                        }
                    }

                    // Make the selected row's action layout visible
                    actionLayout.setVisibility(View.VISIBLE);
                } else {
                    actionLayout.setVisibility(View.GONE);
                }
            }
        });

        copyButton.setOnClickListener(new OnClickListener() {
            @SuppressWarnings("deprecation")
            @Override
            public void onClick(View arg0) {
                Export export = (Export) arg0.getTag();
                String clipboardText = RestInterface.MapURL(m_appPrefs, export.GetExportID());
                int sdk = Build.VERSION.SDK_INT;

                if (sdk < Build.VERSION_CODES.HONEYCOMB) {
                    android.text.ClipboardManager clipboard = (android.text.ClipboardManager) m_context.getSystemService(Context.CLIPBOARD_SERVICE);
                    clipboard.setText(clipboardText);
                } else {
                    ClipboardManager clipboard = (ClipboardManager) m_context.getSystemService(Context.CLIPBOARD_SERVICE);
                    ClipData clipData = ClipData.newPlainText("Export Link", clipboardText);
                    clipboard.setPrimaryClip(clipData);
                }

                Toast.makeText(m_context, "Copied to clipboard", Toast.LENGTH_SHORT).show();
            }
        });

        shareButton.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View arg0) {
                Export export = (Export) arg0.getTag();
                String shareText = m_appPrefs.GetShareIntro() + ": " + RestInterface.MapURL(m_appPrefs, export.GetExportID());

                Intent sendIntent = new Intent();
                sendIntent.setAction(Intent.ACTION_SEND);
                sendIntent.putExtra(Intent.EXTRA_SUBJECT, m_appPrefs.GetShareSubject());
                sendIntent.putExtra(Intent.EXTRA_TEXT, shareText);
                sendIntent.setType("text/plain");
                m_context.startActivity(Intent.createChooser(sendIntent, m_context.getResources().getText(R.string.share_dialog_title)));
            }
        });

        viewButton.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View arg0) {
                Export export = (Export) arg0.getTag();
                String url = RestInterface.MapURL(m_appPrefs, export.GetExportID());
                Intent i = new Intent(Intent.ACTION_VIEW);

                i.setData(Uri.parse(url));
                m_context.startActivity(i);
            }
        });

        deleteButton.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View arg0) {
                Export export = (Export) arg0.getTag();

                new SimpleWebCommand(m_context, RestInterface.DeleteExportID(m_appPrefs, export.GetExportID()), new HttpStatusResponseHandler() {

                    @Override
                    public void onResponseReceived(int returnCode) {
                        if (returnCode == 200) {
                            Toast.makeText(m_context, "Deletion Success", Toast.LENGTH_SHORT).show();
                        } else {
                            Toast.makeText(m_context, "Deletion Failed", Toast.LENGTH_SHORT).show();
                        }
                        m_exportsChangedListener.onExportsChanged();
                    }
                }).execute();
            }
        });

        startText.setText("Start: " + formatDate(currentExport.GetStartTime()));
        endText.setText("End: " + formatDate(currentExport.GetEndTime()));

        return rowView;
    }

    private String formatDate(Date date) {
        Date now = new Date();

        if (isSameDay(now, date)) {
            return android.text.format.DateFormat.format("h:mm aa", date).toString();
        } else if (isTomorrow(now, date)) {
            return "Tomorrow " + android.text.format.DateFormat.format("h:mm aa", date).toString();
        } else if (isTomorrow(date, now)) {
            return "Yesterday " + android.text.format.DateFormat.format("h:mm aa", date).toString();
        } else if (date.getTime() == 0) {
            return "Beginning of time";
        } else if (date.getTime() >= (now.getTime() + (10 * 365 * 24 * 3600 * 1000))) {
            return "Forever";
        } else {
            return android.text.format.DateFormat.format("MMMM dd, yyyy h:mm aa", date).toString();
        }
    }

    private boolean isSameDay(Date first, Date second) {
        Calendar firstCal = Calendar.getInstance();
        firstCal.setTime(first);

        Calendar secondCal = Calendar.getInstance();
        secondCal.setTime(second);

        return (firstCal.get(Calendar.DAY_OF_YEAR) == secondCal.get(Calendar.DAY_OF_YEAR)) && (firstCal.get(Calendar.YEAR) == secondCal.get(Calendar.YEAR));
    }

    private boolean isTomorrow(Date first, Date second) {
        Date tomorrow = new Date(first.getTime() + 24 * 3600 * 1000);

        return isSameDay(tomorrow, second);
    }
}
