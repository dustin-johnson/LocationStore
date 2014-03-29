<!--
    LocationStore - Copyright (C) 2013  Dustin Johnson

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
-->

<!DOCTYPE html>
<?php
    $includeInputsAsJavascript = true;
    include "standard.php";
    include "input.php";
?>
<html>
    <head>
        <meta charset="utf-8" name="viewport" content="initial-scale=1.0, user-scalable=no" />
        <link rel="shortcut icon" href="favicon.ico" />
        <title>Location</title>
        <style type="text/css">
            #over_map_upperLeft { position: absolute; top: 8px; left: 40px; z-index: 99; }
            html { height: 100% }
            body { height: 100%; margin: 0; padding: 0 }
        </style>
        <link href="map.css" rel="stylesheet" type="text/css" />
        <link href="lib/toastr/toastr.css" rel="stylesheet" type="text/css" />
        <link href="lib/impromptu/jquery-impromptu.css" rel="stylesheet" type="text/css" />
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>

        <!--[if lte IE 8]><script language="javascript" type="text/javascript" src="lib/flot/excanvas.min.js"></script><![endif]-->
        <script language="javascript" type="text/javascript" src="lib/flot/jquery.flot.js"></script>
        <script language="javascript" type="text/javascript" src="lib/flot/jquery.flot.time.js"></script>
        <script language="javascript" type="text/javascript" src="lib/flot/jquery.flot.crosshair.js"></script>

        <script type="text/javascript">
            var m_Map;                           // Global map handle
            var m_DirectionsService;             // Used for trip time estimations
            var m_ClientLocationMarker;          // Used to draw the viewer's (client's) location as a blue dot on the map
            var m_LastTimestamp = 0;             // Used to make sure we don't keep re-drawing already drawn markers when receiving new locations
            var m_UpdateIntervalMs = 5 * 1000;   // Query the server for new locations at this period
            var m_Path;                          // Draws the path between marker locations
            var m_InfoWindow;                    // This infoWindow is used for all markers
            var m_AccuracyCircle;                // This circle is used for all markers
            var m_IntervalCallback;              // Used to cancel the refresh callback if needed
            var m_GraphWindowHeightPercent = 30; // The hieght (in window height percent) that the graph window will expand to
            var m_SelectedMarkerInfo = null;

            // Location Arrays
            var m_rawPositionsArray = new Array(); // Hold all raw positions that come in from the server.  This will get used to generate the rest of the arrays.
            var m_MarkerArray = new Array();       // Hold all locations shown on the map
            var m_LatLngArray = new Array();       // Hold all lat/lng positions for the markers so the path between them can be updated easily

            var m_MouseDownLatLng;
            var m_SelectionRect; // Used when a selection is designated
            var m_SelectingRect; // Used while the user is dragging a selection
            var m_SelectingSpinner;
            var m_IsRectSelectionEnabled = false;
            var m_IsSelecting = false;

            var m_Graph = null;
            var m_GraphInnerCanvas;
            var m_GraphLegends;
            var m_GraphCursorPosition = null;
            var m_GraphUpdateLegendTimeout = null;
            var m_GraphSeries = [
                                    {
                                        label: "Speed = 0.0 mph",
                                        data: [[0, 0]]
                                    }
                                ];

            debug = function (log_txt) {
                if (typeof window.console != 'undefined') {
                    console.log(log_txt);
                }
            }

            // Main initialization entry point
            $(document).ready(function() {
                m_Map = new google.maps.Map(document.getElementById("map_canvas"), {center: new google.maps.LatLng(45.70953575956707, -121.50810241699219), // Center around Hood River until the location set is downloaded
                                                                                    zoom: 12,
                                                                                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                                                                                    panControl: false,
                                                                                    scaleControl: true});
                m_DirectionsService = new google.maps.DirectionsService();
                m_LatLngArray = new Array();
                m_Path = new google.maps.Polyline({strokeColor: "#1F497D",
                                                   strokeOpacity: 0.5,
                                                   strokeWeight: 4});
                m_InfoWindow = new google.maps.InfoWindow;
                m_AccuracyCircle = new google.maps.Circle({strokeColor: "#1F497D",
                                                           strokeOpacity: 0.5,
                                                           strokeWeight: 2,
                                                           fillColor: "#1F497D",
                                                           clickable: false,
                                                           fillOpacity: 0.15,
                                                           map: m_Map});
                m_Path.setMap(m_Map);

                // Init toast overlays
                toastr.options.positionClass = 'toast-top-right';
                toastr.options.timeOut = 2000;
                toastr.options.onclick = function () {
                    if (m_MarkerArray.length > 0) {
                        m_Map.setCenter(m_MarkerArray[m_MarkerArray.length-1].getPosition());
                        toastr.clear();
                    }
                };

                // Initialize the client location marker
                m_ClientLocationMarker = new google.maps.Marker({
                    clickable: false,
                    icon: {
                        url: 'markers/clientLocation.png',
                        anchor: new google.maps.Point(8, 8)
                    },
                    shadow: null,
                    zIndex: 999,
                    title: 'Your Location',
                    map : m_Map
                });

                // Make sure we capture the expected range of data
                if (isInputMinLocationTimestampSet) {
                    m_LastTimestamp = inputMinLocationTimestamp;
                }

                // Default to inactive with a 0ms settling time.
                fadeStatusIndicator("statusCircle", "Inactive", 0, null);

                // Request updated location from the device
                requestUpdatedLocation();

                // Get current location
                updateTrack();

                // If we have a hope of receiving more locations (because the end time isn't specified) setup the refrash callback
                if (!isInputMaxLocationTimestampSet) {
                    m_IntervalCallback = window.setInterval(function(){updateTrack()}, m_UpdateIntervalMs);
                }

                //
                // Init the settings menu
                //

                var settingsMenuBase = $("#settingsMenuBase");
                settingsMenuBase.disableSelection();
                settingsMenuBase._openHeight = settingsMenuBase.height();
                settingsMenuBase._closedHeight = 33;
                settingsMenuBase._openWidth = settingsMenuBase.width();
                settingsMenuBase._closedWidth = 40;

                settingsMenuBase.css("height", settingsMenuBase._closedHeight);
                settingsMenuBase.css("width", settingsMenuBase._closedWidth);
                settingsMenuBase.css("visibility", "visible");

                $("#settingsMenuExpandButton").click(function(event) {
                    if (settingsMenuBase.height() <= 50) {
                        settingsMenuBase.animate({"height": settingsMenuBase._openHeight, "width": settingsMenuBase._openWidth}, 100);
                    } else {
                        settingsMenuBase.animate({"height": settingsMenuBase._closedHeight, "width": settingsMenuBase._closedWidth}, 60);
                    }
                });

                var graphContainer = $("#graph_container");
                var graphOuterCanvas = $("#graph_outer_canvas");
                    m_GraphInnerCanvas = $("#graph_inner_canvas");
                var graphExpandButtonImg = $("#graphExpandButtonImg");
                var graphTabBase = $("#graphTabBase")
                var mapContainer = $("#map_container");

                var downHandle = $("<img />").attr('src', "img/arrow_down.png"); // Preload the image so we can swap it out quickly when the menu reaches the top
                graphTabBase.disableSelection();

                graphTabBase.click(function(event) {
                    if (graphContainer.height() <= 5) {
                        graphContainer.css("display", "block");
                        m_GraphInnerCanvas.css("display", "block"); // Unhide the canvas as well so we can query it in the resizeGraph function

                        graphContainer.animate({"height": m_GraphWindowHeightPercent + "%"}, 250, "swing", function(){
                            redrawGraph();
                        });

                        mapContainer.animate({"height": 100 - m_GraphWindowHeightPercent + "%"}, 250, "swing", function() {google.maps.event.trigger(m_Map, "resize");});

                        graphExpandButtonImg.attr("src", "img/arrow_down.png");
                        redrawGraph();
                    } else {
                        graphContainer.animate({"height": "0%"}, 120);
                        mapContainer.animate({"height": "100%"}, 120, "swing", function() {
                            google.maps.event.trigger(m_Map, 'resize');
                            graphContainer.css("display", "none");
                            m_GraphInnerCanvas.css("display", "none"); // Hide the canvas as well so we can query it in the resizeGraph function
                        });
                        graphExpandButtonImg.attr("src", "img/arrow_up.png");
                    }
                });

                m_SelectionRect = new google.maps.Rectangle({map: m_Map, fillOpacity: 0.05, strokeWeight: 1, clickable: false});
                m_SelectingRect = new google.maps.Rectangle({map: m_Map, fillOpacity: 0.10, strokeWeight: 1, clickable: false, fillColor: "#2160A3", strokeColor: "#2160A3"});
                google.maps.event.addListener(m_Map,   "mousemove", function(event) {mouseMove(event);});
                google.maps.event.addListener(m_Map,   "mousedown", function(event) {mouseDown(event);});
                google.maps.event.addListener(m_Map,   "click",     function(event) {mapClick(event);});
                google.maps.event.addListener(m_Map,   "mouseup",   function(event) {mouseUp(event);});

                // Register all keys to cancel rectangle selection as the escape key is unreliable for Chrome
                document.onkeypress = function(e) {
                    enableSelection(false);
                };
                
                
                
                
                
                
                
                
                
                
                
                
                
                
                
                
                redrawGraph();
                
                
                
                
                
                
                
                
                
                
                
                
                
            });

            /// Start - Graph routines
            //////////////////////////////////////////////
            jQuery(window).on('resize', function() {
                redrawGraph()
            });

            function redrawGraph() {
                if (m_GraphInnerCanvas.css("display") != "none" && m_GraphInnerCanvas.width() > 0 && m_GraphInnerCanvas.height() > 0) {
                    if (m_Graph != null) {
                        m_Graph.shutdown();
                        // Completely delete the canvas before recreating it.  This prevents two charts from showing up when viewed on Android 4.2.
                        m_GraphInnerCanvas.empty();
                    }

                    m_Graph = $.plot(m_GraphInnerCanvas, [ m_GraphSeries[0] ], {
                        series: {
                            shadowSize: 0 // Drawing is faster without shadows
                        },
                        crosshair: {
                            mode: "x"
                        },
                        grid: {
                            hoverable: true,
                            clickable: true,
                            autoHighlight: false,
                            borderWidth: {top: 0, left: 1, bottom: 1, right: 0}
                        },
                        xaxis: {
                            mode: "time",
                            timezone: "browser",
                            twelveHourClock: true
                        },/*
                        selection: {
                            mode: "x",
                            color: "#86C2F4"
                        },*/
                        colors: [ "#4682B4" /*SteelBlue */ ]
                    });

                    m_GraphInnerCanvas.bind("plothover",  function (event, pos, item) {
                        m_GraphCursorPosition = pos;
                        if (!m_GraphUpdateLegendTimeout) {
                            m_GraphUpdateLegendTimeout = setTimeout(updateGraphLegend, 100);
                        }
                    });

                    m_GraphInnerCanvas.bind("plotclick",  function (event, pos, item) {
                        selectPlotPoint(pos.x);
                    });

                    m_GraphLegends = $("#graph_inner_canvas .legendLabel");
                    m_GraphLegends.each(function () {
                        // Fix the legend widths so they don't jump around as the user moves the cursor
                        $(this).css('width', $(this).width() + $(this).width() / $(this).text().length * 4); // Add an additional ~4 characters to the length of the string to allow for numbers in the 100's
                    });

                    refreshGraphCursorLock();
                }
            }

            function updateGraphLegend() {
                m_GraphUpdateLegendTimeout = null;

                if (!isItemSelected()) {
                    var pos = m_GraphCursorPosition;

                    var axes = m_Graph.getAxes();
                    if (pos.x < axes.xaxis.min || pos.x > axes.xaxis.max ||
                        pos.y < axes.yaxis.min || pos.y > axes.yaxis.max) {
                        return;
                    }

                    var dataset = m_Graph.getData();
                    for (var i = 0; i < dataset.length; ++i) {
                        var series = dataset[i];
                        var dataIndex = getDataIndexFromSeries(series.data, pos.x);

                        // If we have a valid data index (getDataIndexFromSeries returns -1 on failure)
                        if (dataIndex >= 0) {
                            // Now Interpolate
                            var interpolatedValue;
                            var p1 = series.data[dataIndex];
                            var p2 = series.data[dataIndex + 1];

                            if (p1 == null) {
                                interpolatedValue = p2[1];
                            } else if (p2 == null) {
                                interpolatedValue = p1[1];
                            } else {
                                interpolatedValue = p1[1] + (p2[1] - p1[1]) * (pos.x - p1[0]) / (p2[0] - p1[0]);
                            }

                            m_GraphLegends.eq(i).text(series.label.replace(/=.*[0-9]\.*[0-9]*/, "= " + interpolatedValue.toFixed(1)));
                        }
                    }
                }
            }

            function getDataIndexFromSeries(series, unixTimestamp) {
                var lowIndex = 0;
                var highIndex = series.length - 1;

                // continually narrow search until just one element remains
                while (lowIndex < highIndex) {
                    var middleIndex = Math.floor((lowIndex + highIndex) / 2);

                    if (series[middleIndex][0] < unixTimestamp) {
                        lowIndex = middleIndex + 1;
                    } else {
                        highIndex = middleIndex;
                    }
                }

                if (highIndex == lowIndex) {
                    // Make sure we pick the index that occured just before or on the time given by unixTimestamp
                    if (series[lowIndex][0] > unixTimestamp && lowIndex > 0) {
                        return lowIndex - 1;
                    } else {
                        return lowIndex;
                    }
                } else {
                    return -1;
                }
            }
            //////////////////////////////////////////////
            /// End - Graph routines

            /// Start - Marker selection routines
            //////////////////////////////////////////////
            function setSelectedMarker(marker) {
                unselectItem();

                m_SelectedMarkerInfo = {
                    markerRef : marker,
                    oldIcon: marker.getIcon(),
                    oldShadow: marker.getShadow(),
                    oldShape: marker.getShape(),
                    oldZIndex: marker.getZIndex()
                };

                marker.setIcon(new google.maps.MarkerImage("markers/large/blue/standard.png",
                                                           new google.maps.Size(20, 32),    // Size
                                                           new google.maps.Point(0,0),      // Origin
                                                           new google.maps.Point(10, 32))); // Anchor

                marker.setShadow(new google.maps.MarkerImage("markers/large/shadow.png",
                                                             new google.maps.Size(37, 34),    // Size
                                                             new google.maps.Point(0,0),      // Origin
                                                             new google.maps.Point(10, 34))); // Anchor

                marker.setShape({coord: [-1, 0, -1, 24, 19, 24, 19, 0], type: 'poly'});
                marker.setZIndex(99999999999999999); // Bring the marker to the top of the pile.

                refreshGraphCursorLock();
            }

            function getSelectedMarker() {
                if (isItemSelected()) {
                    return m_SelectedMarkerInfo.markerRef;
                } else {
                    return null;
                }
            }

            function selectPlotPoint(unixTimestamp) {
                unselectItem();

                var markerIndex = getMarkerIndex(unixTimestamp);

                // If we have a valid marker index (getMarkerIndex returns -1 on failure)
                if (markerIndex >= 0) {
                    setSelectedMarker(m_MarkerArray[markerIndex]);
                }
            }

            function refreshGraphCursorLock() {
                if (isItemSelected()) {
                    lockGraphCursor(m_SelectedMarkerInfo.markerRef.get("locationData")['locationTimestamp']);
                }
            }

            function lockGraphCursor(unixTimestamp) {
                var dataIndex = getDataIndexFromSeries(m_GraphSeries[0].data, unixTimestamp);

                // If we have a valid data index (getDataIndexFromSeries returns -1 on failure)
                if (dataIndex >= 0) {
                    m_Graph.lockCrosshair({
                        x: m_GraphSeries[0].data[dataIndex][0],
                        y: m_GraphSeries[0].data[dataIndex][1]
                    });
                    m_GraphLegends.eq(0).text(m_GraphSeries[0].label.replace(/=.*[0-9]\.*[0-9]*/, "= " + m_GraphSeries[0].data[dataIndex][1].toFixed(1)));
                }
            }

            function isItemSelected() {
                return m_SelectedMarkerInfo != null;
            }

            function unselectItem() {
                if (isItemSelected()) {
                    m_SelectedMarkerInfo.markerRef.setIcon(m_SelectedMarkerInfo.oldIcon);
                    m_SelectedMarkerInfo.markerRef.setShadow(m_SelectedMarkerInfo.oldShadow);
                    m_SelectedMarkerInfo.markerRef.setShape(m_SelectedMarkerInfo.oldShape);
                    m_SelectedMarkerInfo.markerRef.setZIndex(m_SelectedMarkerInfo.oldZIndex);

                    m_SelectedMarkerInfo = null;
                    m_Graph.unlockCrosshair();
                    m_Graph.clearCrosshair();
                }
            }

            function mapClick(event) {
                unselectItem();
            }

            function getMarkerIndex(unixTimestamp) {
                var lowIndex = 0;
                var highIndex = m_MarkerArray.length - 1;

                // continually narrow search until just one element remains
                while (lowIndex < highIndex) {
                    var middleIndex = Math.floor((lowIndex + highIndex) / 2);

                    if (m_MarkerArray[middleIndex].get("locationData")['locationTimestamp'] < unixTimestamp) {
                        lowIndex = middleIndex + 1;
                    } else {
                        highIndex = middleIndex;
                    }
                }

                if (highIndex == lowIndex) {
                    // Make sure we pick the index that occured just before or on the time given by unixTimestamp
                    if (m_MarkerArray[lowIndex].get("locationData")['locationTimestamp'] > unixTimestamp && lowIndex > 0) {
                        return lowIndex - 1;
                    } else {
                        return lowIndex;
                    }
                } else {
                    return -1;
                }
            }
            //////////////////////////////////////////////
            /// End - Marker selection routines

            /// Start - Rectangle selection routines
            //////////////////////////////////////////////
            function mouseMove(event) {
                if (m_IsSelecting) {
                    m_SelectingRect.setBounds(new google.maps.LatLngBounds(new google.maps.LatLng(Math.min(m_MouseDownLatLng.lat(), event.latLng.lat()),
                                                                                                  Math.min(m_MouseDownLatLng.lng(), event.latLng.lng())),
                                                                           new google.maps.LatLng(Math.max(m_MouseDownLatLng.lat(), event.latLng.lat()),
                                                                                                  Math.max(m_MouseDownLatLng.lng(), event.latLng.lng()))));
                }
            }

            function mouseDown(event) {
                if (m_IsRectSelectionEnabled) {
                    m_IsSelecting = true;
                    m_MouseDownLatLng = event.latLng;
                    m_Map.setOptions({draggable: false});

                    if (m_SelectingSpinner == null) {
                        var image = new google.maps.MarkerImage("img/spinner/Snakes/animated_GIF/32x32.gif",
                                                                new google.maps.Size(32, 32),   // Size
                                                                new google.maps.Point(0, 0),    // Origin
                                                                new google.maps.Point(16, 16)); // Anchor
                        m_SelectingSpinner = new google.maps.Marker({map: m_Map,
                                                                     icon: image,
                                                                     visible: false,
                                                                     optimized: false,
                                                                     flat: true,
                                                                     clickable: false,
                                                                     zIndex: 999999});
                    }
                }
            }

            function mouseUp(event) {
                if (m_IsSelecting) {
                    m_IsSelecting = false;
                    enableSelection(false);
                    m_Map.setOptions({draggable: true});

                    isInputMaxLatSet = true;
                    isInputMinLatSet = true;
                    isInputMaxLonSet = true;
                    isInputMinLonSet = true;

                    m_SelectionRect.setBounds(m_SelectingRect.getBounds());
                    inputMaxLat_deg = m_SelectionRect.getBounds().getNorthEast().lat();
                    inputMinLat_deg = m_SelectionRect.getBounds().getSouthWest().lat();
                    inputMaxLon_deg = m_SelectionRect.getBounds().getNorthEast().lng();
                    inputMinLon_deg = m_SelectionRect.getBounds().getSouthWest().lng();
                    m_SelectingRect.setBounds(new google.maps.LatLngBounds());

                    inputCount = 99999999; // Unlimit the number of locations able to be displayed

                    // Make sure we capture the expected range of data
                    if (isInputMinLocationTimestampSet) {
                        m_LastTimestamp = inputMinLocationTimestamp;
                    } else {
                        m_LastTimestamp = 0;
                    }

                    clearMap(true);
                    m_SelectingSpinner.setPosition(new google.maps.LatLng((inputMaxLat_deg + inputMinLat_deg) / 2,
                                                                          (inputMaxLon_deg + inputMinLon_deg) / 2));
                    // m_SelectingSpinner.setVisible(true);

                    updateTrack(function(locationData) {
                        if (locationData && (!locationData['locations'] || locationData['locations'].length == 0)) {
                            toastr.error("No locations found in the selected area");
                        }
                        m_SelectingSpinner.setVisible(false);
                    });
                }
            }

            function enableSelection(enabled) {
                m_IsRectSelectionEnabled = enabled;
                $("#map_canvas").css({cursor: (enabled ? 'crosshair' : 'auto')});
                m_Map.setOptions({draggableCursor: (enabled ? 'crosshair' : null)});

                if (!enabled && m_IsSelecting) {
                    m_IsSelecting = false;
                    m_SelectingRect.setBounds(new google.maps.LatLngBounds());
                }
            }
            //////////////////////////////////////////////
            /// End - Rectangle selection routines

            function updateClientPosition(position) {
                var clientLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

                if (!clientLocation.equals(m_ClientLocationMarker.getPosition())) {
                    m_ClientLocationMarker.setPosition(clientLocation);
                }
            }

            function getLocationURL(minLocationTimestamp) {
                if (typeof minLocationTimestamp === "undefined" || minLocationTimestamp === null) minLocationTimestamp = inputMinLocationTimestamp;

                var url = "getLocationData.php?count="                + inputCount               +
                                             "&exportID="             + inputExportID            +
                                             "&minAccuracy_m="        + inputMinAccuracy_m       +
                                             "&minLocationTimestamp=" + minLocationTimestamp     +
                                             "&maxLocationTimestamp=" + inputMaxLocationTimestamp;

                if (isInputMaxLatSet && isInputMinLatSet && isInputMaxLonSet && isInputMinLonSet) {
                    url += "&maxLat_deg=" + inputMaxLat_deg +
                           "&minLat_deg=" + inputMinLat_deg +
                           "&maxLon_deg=" + inputMaxLon_deg +
                           "&minLon_deg=" + inputMinLon_deg;
                }

                return url;
            }

            /// Start - Location manipulation routines
            //////////////////////////////////////////////
            function updateTrack(resultHandler) {
                downloadUrl(getLocationURL(m_LastTimestamp + 1), function(data, statusCode) {
                    if (statusCode == 200) {
                        var locationData = JSON.parse(data.responseText);

                        if (resultHandler) {
                            resultHandler(locationData);
                        }

                        // If we haven't gotten any points yet, configure the view to show them.
                        // If we already have at least one point, don't mess with the user by moving the view.
                        if (m_rawPositionsArray.length == 0) {
                            var userID = locationData['userID']
                            var bounds = locationData['bounds'];

                            if (userID) {
                                document.title = userID + "'s Location";
                            }

                            if (bounds) {
                                m_Map.fitBounds(new google.maps.LatLngBounds(new google.maps.LatLng(bounds['southWest']['lat_deg'], bounds['southWest']['lon_deg']),
                                                                             new google.maps.LatLng(bounds['northEast']['lat_deg'], bounds['northEast']['lon_deg'])));
                            }
                        }

                        var newLocations = locationData['locations'];
                        if (newLocations && newLocations.length > 0) {
                            // Notify the user of updated location only if it isn't the first time getting a location.
                            // This prevents a toast for every refresh of the page.
                            if (m_rawPositionsArray.length != 0) {
                                toastr.info("Location updated");
                            }

                            var lengthToRemove = Math.max(m_rawPositionsArray.length + newLocations.length - inputCount, 0);

                            if (lengthToRemove > 0) {
                                m_rawPositionsArray = m_rawPositionsArray.slice(lengthToRemove)
                            }

                            m_rawPositionsArray = m_rawPositionsArray.concat(newLocations);

                            // Calculate the additional data for the new points
                            // If we had old data that we're adding to, make sure to calculate the data for the last point in the old data too
                            var numLocationsToUpdate = newLocations.length + (m_rawPositionsArray.length > newLocations.length ? 1 : 0);
                            for (var i = m_rawPositionsArray.length - numLocationsToUpdate; i < m_rawPositionsArray.length; i++) {
                                if (i < m_rawPositionsArray.length - 1) {
                                    var location = m_rawPositionsArray[i];
                                    var nextLocation = m_rawPositionsArray[i+1];
                                    var point = new google.maps.LatLng(location['lat_deg'], location['lon_deg']);
                                    var nextPoint = new google.maps.LatLng(nextLocation['lat_deg'], nextLocation['lon_deg']);

                                    location['bearing_d'] = getBearingDegrees(point, nextPoint);
                                    location['distance_m'] = google.maps.geometry.spherical.computeDistanceBetween(point, nextPoint);
                                    location['calcSpeed_mps'] = location['distance_m'] / ((nextLocation['locationTimestamp'] - location['locationTimestamp']) / 1000);
                                }
                            }

                            addLocationsToMap(lengthToRemove, m_rawPositionsArray.slice(m_rawPositionsArray.length - numLocationsToUpdate));
                            addLocationsToGraph(lengthToRemove, m_rawPositionsArray.slice(m_rawPositionsArray.length - newLocations.length));

                            m_LastTimestamp = m_rawPositionsArray[m_rawPositionsArray.length - 1]['locationTimestamp'];
                        }

                        fadeStatusIndicator("statusCircle", "Active", 200, function () {fadeStatusIndicator("statusCircle", "Inactive", m_UpdateIntervalMs, null);});
                    }
                });
            }

            // This function expects to get the number of locations to remove from the map, starting with the oldest location, for 'numLocationsToRemove'
            // The 'newLocations' array is expected to be the new locations, starting with the most recent location already on the map, if one existed.  This is to allow the oldest location to be updated with the new calculated values.
            function addLocationsToMap(numLocationsToRemove, newLocations) {
                if (numLocationsToRemove >= 0 && newLocations && newLocations.length > 0) {
                    // Step 1 - Store the former most recent location before updating for possible use later
                    var formerFinalLocation = (m_LatLngArray.length > 0 ? m_LatLngArray[m_LatLngArray.length-1] : null);

                    // Step 2 - Remove old markers
                    var numLocationsToRemove = Math.min(numLocationsToRemove, m_MarkerArray.length);
                    if (numLocationsToRemove > 0) {
                        for (var i = 0; i < numLocationsToRemove; i++) {
                            m_MarkerArray[i].setMap(null);
                        }

                        m_MarkerArray = m_MarkerArray.slice(numLocationsToRemove);
                        m_LatLngArray = m_LatLngArray.slice(numLocationsToRemove);
                    }

                    //  Step 3 - Pull the most recent marker from the map as it has the ending icon and we want to remove that.
                    if (newLocations.length > 0 && m_MarkerArray.length > 0) {
                        var lastMarker = m_MarkerArray.pop();
                        lastMarker.setMap(null);
                        m_LatLngArray.pop();

                        if (lastMarker.get("locationData")['locationTimestamp'] != newLocations[0]['locationTimestamp']) {
                            debug("Assert failed - addLocationsToMap did not get passed the most recent location as the first item in newLocations");
                        }
                    }

                    // Step 4 - Add the new locations to the map
                    for (var i = 0; i < newLocations.length; i++) {
                        var marker;

                        if (i == newLocations.length - 1) {
                            marker = addMarker(newLocations[i], true, newLocations[i]['locationTimestamp']); // +10 to make sure we're above the accuracy circle, etc.
                        } else {
                            marker = addMarker(newLocations[i], false, newLocations[i]['locationTimestamp']); // +10 to make sure we're above the accuracy circle, etc.
                        }

                        // If the time we just added is the same time as the selected marker, then we just reloaded the selected marker.  Update the system accordingly.
                        var selectedMarker = getSelectedMarker();
                        if (selectedMarker != null && newLocations[i]['locationTimestamp'] == selectedMarker.get("locationData")['locationTimestamp']) {
                            setSelectedMarker(marker);
                        }
                    }

                    // Step 7 - Grow existing view bounds to encompass the new points, if the last point was in the bounds already.
                    // I hope this will lead to a natural user experience that allows the user to explore the map without having to worry
                    // about his view being yanked from him every time there is a location update.  This will likely need more thought.
                    if (formerFinalLocation != null && m_Map.getBounds() != null && !m_Map.getBounds().contains(m_LatLngArray[m_LatLngArray.length-1]) && m_Map.getBounds().contains(formerFinalLocation)) {
                        m_Map.fitBounds(m_Map.getBounds().extend(m_LatLngArray[m_LatLngArray.length-1]));
                    }
                } else {
                    for (var i = 0; i < m_MarkerArray.length; i++) {
                        m_MarkerArray[i].setMap(null);
                    }

                    m_MarkerArray = new Array();
                    m_LatLngArray = new Array();
                    m_LastTimestamp = inputMinLocationTimestamp;
                }

                m_Path.setPath(m_LatLngArray);
            }

            // This function expects to get the number of locations to remove from the graph, starting with the oldest location, for 'numLocationsToRemove'
            // The 'newLocations' array is expected to be just the new locations.
            function addLocationsToGraph(numLocationsToRemove, newLocations) {
                if (numLocationsToRemove >= 0 && newLocations && newLocations.length > 0) {
                    // Check to see if we still have the dummy initial value.  If so, make sure to remove it.
                    if (m_GraphSeries[0].data.length == 1 && m_GraphSeries[0].data[0][0] == 0 && m_GraphSeries[0].data[0][1] == 0)
                    {
                        numLocationsToRemove++;
                    }

                    // Step 2 - Remove old values
                    var numLocationsToRemove = Math.min(numLocationsToRemove, m_GraphSeries[0].data.length);
                    if (numLocationsToRemove > 0) {
                        for (var i = 0; i < m_GraphSeries.length; i++) {
                            m_GraphSeries[i].data = m_GraphSeries[i].data.slice(numLocationsToRemove);
                        }
                    }

                    // Step 4 - Add the new data to the series
                    for (var i = 0; i < newLocations.length; i++) {
                        var location = newLocations[i];

                        if (location['speed_mps'] != null) {
                            m_GraphSeries[0].data.push([location['locationTimestamp'], (location['speed_mps'] * 3600) / 1609.34]);
                        } else if (location['calcSpeed_mps'] != null) {
                            m_GraphSeries[0].data.push([location['locationTimestamp'], (location['calcSpeed_mps'] * 3600) / 1609.34]);
                        }

                        //if (location['alt_m'] != null) {
                        //    m_GraphSeries[1].data.push({x: location['locationTimestamp'] / 1000 - timezoneOffset_s, y: location['alt_m'] * 3.28084});
                        //}
                    }
                } else {
                    for (var i = 0; i < m_GraphSeries.length; i++) {
                        m_GraphSeries[i].data = [[0, 0]];
                    }
                }

                redrawGraph();
            }

            function addMarker(location, isFinalMarker, zIndex) {
                var marker;
                var point = new google.maps.LatLng(location['lat_deg'], location['lon_deg']);
                var timestamp = new Date(location['locationTimestamp']);
                var html = "<font class=\"markerInfoCategory\">Time:</font> <font class=\"markerInfoValue\">" + " " + hours24ToHours12(timestamp.getHours()) + ":" + padNumber(timestamp.getMinutes(), 2) + 
                               ":" + padNumber(timestamp.getSeconds(), 2) + " " + (timestamp.getHours() > 11 ? "PM" : "AM") + " " +
                               " " + (timestamp.getMonth() + 1) + "/" + timestamp.getDate() + "/" + timestamp.getFullYear() + "</font><br/>";
                location['accuracy_m'] = ('accuracy_m' in location) ? location['accuracy_m'] : 1000;

                html += "<font class=\"markerInfoCategory\">Timestamp:</font> <font class=\"markerInfoValue\">" + location['locationTimestamp'] + "</font><br/>";
                html += "<font class=\"markerInfoCategory\">Accuracy:</font> <font class=\"markerInfoValue\">" + Math.round((location['accuracy_m'] * 10) / 10.0) + " m</font><br/>";

                if (location['speed_mps'] != null) {
                    html += "<font class=\"markerInfoCategory\">Speed:</font> <font class=\"markerInfoValue\">" + ((location['speed_mps'] * 3600) / 1609.34).toFixed(1) + " mph</font><br/>";
                } else if (location['calcSpeed_mps'] != null) {
                    html += "<font class=\"markerInfoCategory\">Calculated Speed:</font> <font class=\"markerInfoValue\">" + ((location['calcSpeed_mps'] * 3600) / 1609.34).toFixed(1) + " mph</font><br/>";
                }

                if (location['alt_m'] != null) {
                    html += "<font class=\"markerInfoCategory\">Altitude:</font> <font class=\"markerInfoValue\">" + (location['alt_m'] * 3.28084).toFixed(0) + " feet</font><br/>";
                }

                if (location['battery_percent'] != null) {
                    html += "<font class=\"markerInfoCategory\">Battery:</font> <font class=\"markerInfoValue\">" + location['battery_percent'].toFixed(0) + "%</font><br/>";
                }

                html += "<font class=\"markerInfoCategory\">Location Source:</font> <font class=\"markerInfoValue\">" + location['provider'] + "</font><br/>";
                
                html += "<br/>";

                if (location['bearing_d'] != null) {
                    html += "<font class=\"markerInfoCategory\">Calculated bearing:</font> <font class=\"markerInfoValue\">" + Math.round(location['bearing_d']) + "&deg;</font><br/>";
                }

                if (location['distance_m'] != null) {
                    var distanceMeters = location['distance_m'];
                    if (distanceMeters > 1000) {
                        html += "<font class=\"markerInfoCategory\">Distance to next:</font> <font class=\"markerInfoValue\">" + (distanceMeters / 1000).toFixed(2) + " km</font><br/>";
                    } else {
                        html += "<font class=\"markerInfoCategory\">Distance to next:</font> <font class=\"markerInfoValue\">" + distanceMeters.toFixed(1) + " m</font><br/>";
                    }
                }

                if (isFinalMarker) {
                    marker = addFinalMarker(m_Map, point, zIndex);
                    m_AccuracyCircle.setCenter(point);
                    m_AccuracyCircle.setRadius(location['accuracy_m']);
                } else {
                    marker = addStandardMarker(m_Map, point, location['bearing_d'], zIndex);
                }

                bindMetadata(marker, html, location);

                m_MarkerArray.push(marker);
                m_LatLngArray.push(point);
                
                return marker;
            }

            function addStandardMarker(map, point, bearingDegrees, zIndex) {
                var direction = getDirection(bearingDegrees);

                var image = new google.maps.MarkerImage("markers/small/red/" + direction + ".png",
                                                        new google.maps.Size(13, 20),  // Size
                                                        new google.maps.Point(0,0),    // Origin
                                                        new google.maps.Point(6, 20)); // Anchor

                var shadow = new google.maps.MarkerImage("markers/small/shadow.png",
                                                         new google.maps.Size(22, 20),  // Size
                                                         new google.maps.Point(0,0),    // Origin
                                                         new google.maps.Point(6, 20)); // Anchor
                var clickable = {coord: [-1, 0, -1, 13, 11, 13, 11, 0], type: 'poly'};

                var marker = new google.maps.Marker({map: map,
                                                     position: point,
                                                     icon: image,
                                                     shadow: shadow,
                                                     shape: clickable,
                                                     zIndex: zIndex});

                return marker;
            }

            function addFinalMarker(map, point, zIndex) {
                var image = new google.maps.MarkerImage("markers/large/red/final.png",
                                                        new google.maps.Size(20, 32),   // Size
                                                        new google.maps.Point(0,0),     // Origin
                                                        new google.maps.Point(10, 32)); // Anchor

                var shadow = new google.maps.MarkerImage("markers/large/shadow.png",
                                                         new google.maps.Size(37, 34),   // Size
                                                         new google.maps.Point(0,0),     // Origin
                                                         new google.maps.Point(10, 34)); // Anchor
                var clickable = {coord: [-1, 0, -1, 24, 19, 24, 19, 0], type: 'poly'};

                var marker = new google.maps.Marker({map: map,
                                                     position: point,
                                                     icon: image,
                                                     shadow: shadow,
                                                     shape: clickable,
                                                     zIndex: zIndex});

                return marker;
            }

            function bindMetadata(marker, html, locationData) {
                marker.set("infoHTML", html);
                marker.set("locationData", locationData);

                google.maps.event.addListener(marker, 'click', function() {
                    setSelectedMarker(marker);

                    m_InfoWindow.setContent(marker.get("infoHTML"));
                    m_InfoWindow.open(m_Map, marker);
                    m_AccuracyCircle.setCenter(marker.getPosition());
                    m_AccuracyCircle.setMap(m_Map);
                    m_AccuracyCircle.setRadius(marker.get("locationData")['accuracy_m']);
                });
            }

            function clearMap(removeLastPoint) {
                if (m_rawPositionsArray.length > 0) {
                    m_InfoWindow.close();
                    m_AccuracyCircle.setMap(null);

                    if (removeLastPoint) {
                        m_rawPositionsArray = new Array();
                    } else {
                        m_rawPositionsArray = m_rawPositionsArray.slice(m_rawPositionsArray.length - 1);
                    }

                    addLocationsToMap(-1, null);
                    addLocationsToGraph(-1, null);
                }
            }

            function togglePath() {
                if (m_Path.getMap() == m_Map) {
                    m_Path.setMap(null);
                } else {
                    m_Path.setMap(m_Map);
                }
            }
            //////////////////////////////////////////////
            /// End - Location manipulation routines

            /// Start - Utility functions
            //////////////////////////////////////////////
            function downloadUrl(url, callback) {
                var request = window.ActiveXObject ? new ActiveXObject('Microsoft.XMLHTTP') : new XMLHttpRequest;

                request.onreadystatechange = function() {
                    if (request.readyState == 4) {
                        request.onreadystatechange = doNothing;
                        callback(request, request.status);
                    }
                };

                request.open('GET', url, true);
                request.send(null);
            }

            // From [0-23] to [12,1-11]
            function hours24ToHours12(hours24) {
                var hours12 = hours24 % 12;

                if (hours12 == 0) {
                    hours12 = 12;
                }

                return hours12;
            }

            function padNumber(number, length) {
                var str = "" + number;
                while (str.length < length) {
                    str = "0" + str;
                }

                return str;
            }

            function getDirection(bearingDegrees) {
                var direction = "up";

                if (bearingDegrees < 22.5 || bearingDegrees >= 337.5) {
                    direction = "up";
                } else if (bearingDegrees < 67.5) {
                    direction = "up_right";
                } else if (bearingDegrees < 112.5) {
                    direction = "right";
                } else if (bearingDegrees < 157.5) {
                    direction = "down_right";
                } else if (bearingDegrees < 202.5) {
                    direction = "down";
                } else if (bearingDegrees < 247.5) {
                    direction = "down_left";
                } else if (bearingDegrees < 292.5) {
                    direction = "left";
                } else if (bearingDegrees < 337.5) {
                    direction = "up_left";
                }

                return direction;
            }

            function getBearingDegrees(point1, point2) {
                return (360 + google.maps.geometry.spherical.computeHeading(point1, point2)) % 360; // Normalize to [0-360)
            }

            var isMobile = {
                Android: function() {
                    return navigator.userAgent.match(/Android/i);
                },
                BlackBerry: function() {
                    return navigator.userAgent.match(/BlackBerry/i);
                },
                iOS: function() {
                    return navigator.userAgent.match(/iPhone|iPad|iPod/i);
                },
                Opera: function() {
                    return navigator.userAgent.match(/Opera Mini/i);
                },
                Windows: function() {
                    return navigator.userAgent.match(/IEMobile/i);
                },
                any: function() {
                    return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
                }
            };

            function doNothing() {}
            //////////////////////////////////////////////
            /// End - Utility functions

            /// Start - Status fade routines
            //////////////////////////////////////////////
            function fadeStatusIndicator(id, fadeDirection, fadeDurationMs, fadeCompleteCallback) {
                var element = document.getElementById(id);

                if (element.fadeState == null) {
                    element.fadeState = 0.5;
                }
                element.fadeDirection = fadeDirection;
                element.fadeDurationMs = fadeDurationMs;
                element.fadeCompleteCallback = fadeCompleteCallback;
                element.fadeStartTime = new Date().getTime() - 1;

                if (element.isFading == null || element.isFading == false) {
                    animateStatusIndicator(element, 100);
                }
            }

            function animateStatusIndicator(element, tickLengthMs) {
                var curTime = new Date().getTime();

                element.isFading = true;
                if (element.fadeDirection == "Active"   && element.fadeState >= 1 ||
                    element.fadeDirection == "Inactive" && element.fadeState <= 0) {
                    element.isFading = false;
                    if (element.fadeCompleteCallback != null) {
                        element.fadeCompleteCallback();
                    }
                    return;
                }

                setTimeout(function(){animateStatusIndicator(element, tickLengthMs)}, tickLengthMs);

                element.fadeState = Math.min((curTime - element.fadeStartTime) / element.fadeDurationMs, 1);
                if (element.fadeDirection == "Inactive") {
                    element.fadeState = 1 - element.fadeState;
                }

                setStatusColor(element, element.fadeState);
            }

            function setStatusColor(element, fadeState) {
                var activeColor = 0x2DB82D;
                var inactiveColor = 0xD3D3D3;
                var resultColor = 0;
                var borderColor = 0;

                // Generate intermediate color
                for (var i = 0; i < 3; i++) {
                    resultColor |= (((activeColor  & (0xFF << (i * 8))) >> (i * 8)) *      fadeState +
                                   ((inactiveColor & (0xFF << (i * 8))) >> (i * 8)) * (1 - fadeState)) << (i * 8);
                }

                // Generate border color
                for (var i = 0; i < 3; i++) {
                    borderColor |= Math.max((((resultColor  & (0xFF << (i * 8))) >> (i * 8)) - 0x54), 0) << (i * 8);
                }

                var tempColor = "00000" + resultColor.toString(16).substr(-6);
                element.style.background = "#" + tempColor.substr(tempColor.length - 6);

                tempColor = "00000" + borderColor.toString(16).substr(-6);
                element.style.borderColor = "#" + tempColor.substr(tempColor.length - 6);
            }
            //////////////////////////////////////////////
            /// Start - Status fade routines

            /// Start - Settings menu routines
            //////////////////////////////////////////////
            function showKmlPrompt(baseLink) {
                if (isMobile.any()) {
                    downloadGpsTrack(true, true);
                } else {
                    $.prompt({state0: { title:"Altitude Type",
                                        buttons: { "Snap to ground":true, "Flying":false },
                                        position: { container: '#downloadLinks', x: -270, y: -20, width: 240, arrow: 'rm' },
                                        submit: function(e,v,m,f){
                                            downloadGpsTrack(true, v);
                                        }
                                      }
                             },
                             { opacity: 0.0,
                               overlayspeed: 0,
                               promptspeed: 100,
                               show: "fadeIn"
                             });
                }
            }

            function downloadGpsTrack(isKml, isSnappedToGround) {
                var outputBaseLink = getLocationURL() + "&outputFormat=";

                if (isKml) {
                    window.location = outputBaseLink + "kml&useGroundAlt=" + (isSnappedToGround ? "true" : "false");
                } else {
                    window.location = outputBaseLink + "gpx";
                }
            }

            function routeToHome() {
                if (m_MarkerArray.length > 0) {

                    var request = {origin: m_MarkerArray[m_MarkerArray.length-1].getPosition(),
                                   destination: "1827 Wasco, Hood River, OR 97031",
                                   travelMode: google.maps.DirectionsTravelMode.DRIVING};

                    m_DirectionsService.route(request, function(response, status) {
                        if (status == google.maps.DirectionsStatus.OK) {
                            var leg = response.routes[0].legs[0];
                            $('#TimeToHomeValues').html("Distance: " + leg.distance.text + " <br>" + 
                                                        "Duration: " + leg.duration.text);
                        }
                    });
                }
            }

            // Request updated location from the device
            function requestUpdatedLocation() {
                var forceFixURL = "sendForceLocationFixRequest.php?exportID=" + inputExportID;
                downloadUrl(forceFixURL, function(data) {});
            }
            //////////////////////////////////////////////
            /// End - Settings menu routines

        </script>
        <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDZGWJwMU1CKIIJuBE7n_gVwbs1duEME1k&sensor=false&v=3.11&libraries=geometry"></script>
        <script type="text/javascript" src="lib/json2.js"></script>
        <script type="text/javascript" src="lib/disableSelection.js"></script>
        <script type="text/javascript" src="lib/toastr/toastr.compressed.js"></script>
        <script type="text/javascript" src="lib/impromptu/jquery-impromptu.compressed.js"></script>
    </head>
    <body>
        <div id="map_container">
            <div id="over_map_upperLeft">
                <div id="statusCircle" title="Update Status Indicator"></div>
            </div>
            <div id="map_canvas" style="width:100%; height:100%"></div>
            <div id="settingsMenuBase" style="visibility:hidden">
                <div id="settingsMenuExpandButton">
                    <img src="img/ic_action_settings.png" class="center" width="32" height="32" alt="Settings Menu" title="Settings Menu">
                </div>
                <div class="hr"></div>
                <div class="settingsMenuItem" id="downloadLinks">
                    <p class="settingsMenuItemParagraph">
                        Download<br>
                        <a href="javascript:downloadGpsTrack(false, true);">GPX</a> <a href="javascript:showKmlPrompt();">KML</a>
                     </p>
                </div>
                <?php if ($inputExportID == "tester" || $inputExportID == "phae") { ?>
                <div class="hr"></div>
                <div class="settingsMenuItem">
                    <p class="settingsMenuItemParagraph">
                        Time to Home
                    </p>
                    <p id="TimeToHomeValues" class="settingsMenuItemParagraph">
                        Distance: Unknown <br>
                        Duration: Unknown
                    </p>
                    <p class="settingsMenuItemParagraph">
                        <a href="javascript:routeToHome();">Update</a>
                    </p>
                </div>
                <?php } ?>
                <div class="hr"></div>
                <div class="settingsMenuItem">
                    <p class="settingsMenuItemParagraph">
                        <a href="javascript:enableSelection(true);">Select Region</a>
                     </p>
                </div>
                <div class="hr"></div>
                <div class="settingsMenuItem">
                    <p class="settingsMenuItemParagraph">
                        <a href="javascript:requestUpdatedLocation();">Request Location Update</a>
                     </p>
                </div>
                <div class="hr"></div>
                <div id="settingsMenuFooter"></div>
            </div>
            <div id="graphTabBase">
                <div id="graphExpandButton" class="center">
                    <img id="graphExpandButtonImg" src="img/arrow_up.png" class="graphTabCenter" alt="Show/Hide Graphs" title="Show/Hide Graphs">
                    <img src="img/graphTabDivider.png" class="graphTabCenter" alt="Show/Hide Graphs" title="Show/Hide Graphs">
                    <img src="img/graph_icon.png" class="graphTabCenter" width="32" height="24" alt="Show/Hide Graphs" title="Show/Hide Graphs">
                </div>
            </div>
        </div>
        <div id="graph_container" style="height:0%; display:none;">
            <div class="dark_hr"></div>
            <div id="graph_outer_canvas">
                <div id="graph_inner_canvas"></div>
            </div>
        </div>
    </body>
</html>