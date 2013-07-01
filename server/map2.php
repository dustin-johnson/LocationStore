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
            #map_canvas { height: 100% }
        </style>
        <link href="map.css" rel="stylesheet" type="text/css" />
        <link href="lib/toastr/toastr.css" rel="stylesheet" type="text/css" />
        <link href="lib/impromptu/jquery-impromptu.css" rel="stylesheet" type="text/css" />
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.5.1/leaflet.css" />
        <!--[if lte IE 8]>
            <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.5.1/leaflet.ie.css" />
        <![endif]-->
        <script src="http://cdn.leafletjs.com/leaflet-0.5.1/leaflet.js"></script>
        <script type="text/javascript">
            var m_Map;
            var m_DirectionsService;
            var m_ClientLocationMarker;
            var m_LastTimestamp = 0;
            var m_UpdateIntervalMs = 10 * 1000;
            var m_MarkerArray = new Array();
            var m_LatLngArray;
            var m_Path;
            var m_InfoWindow;
            var m_AccuracyCircle;
            var m_IntervalCallback;

            var m_MouseDownLatLng;
            var m_SelectionRect; // Used when a selection is designated
            var m_SelectingRect; // Used while the user is dragging a selection
            var m_IsRectSelectionEnabled = false;
            var m_IsSelecting = false;

            debug = function (log_txt) {
                if (typeof window.console != 'undefined') {
                    console.log(log_txt);
                }
            }

            $(document).ready(function() {
                m_Map = new L.Map('map_canvas');

                var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
                var osmAttrib='Map data © OpenStreetMap contributors';
                var osm = new L.TileLayer(osmUrl, {attribution: osmAttrib});

                m_Map.setView(new L.LatLng(45.70953575956707, -121.50810241699219), 14);
                m_Map.addLayer(osm);

                m_LatLngArray = new Array();
/*
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

                if (inputDrawLine) {
                    m_Path.setMap(m_Map);
                }

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
*/
                // Default to inactive with a 0ms settling time.
                fadeStatusIndicator("statusCircle", "Inactive", 0, null);

                // Request updated location from the device
                var forceFixURL = "sendForceLocationFixRequest.php?exportID=" + inputExportID
                downloadUrl(forceFixURL, function(data) {});

                // Get current location
                updateTrack();
/*
                if (!isInputMaxLocationTimestampSet) {
                    m_IntervalCallback = window.setInterval(function(){updateTrack()}, m_UpdateIntervalMs);
                }
*/
                var menuBase = $("#menuBase");
                menuBase.disableSelection();
                menuBase._openHeight = menuBase.height();
                menuBase._closedHeight = 33;
                menuBase._openWidth = menuBase.width();
                menuBase._closedWidth = 40;

                menuBase.css("height", menuBase._closedHeight);
                menuBase.css("width", menuBase._closedWidth);
                menuBase.css("visibility", "visible");

                $("#menuExpandButton").click(function(event) {
                    if (menuBase.height() <= 50) {
                        menuBase.animate({"height": menuBase._openHeight, "width": menuBase._openWidth}, 100);
                    } else {
                        menuBase.animate({"height": menuBase._closedHeight, "width": menuBase._closedWidth}, 60);
                    }
                });

/*
                m_SelectionRect = new google.maps.Rectangle({map: m_Map, fillOpacity: 0.05, strokeWeight: 1, clickable: false});
                m_SelectingRect = new google.maps.Rectangle({map: m_Map, fillOpacity: 0.10, strokeWeight: 1, clickable: false, fillColor: "#2160A3", strokeColor: "#2160A3"});
                google.maps.event.addListener(m_Map,   'mousemove', function(event) {mouseMove(event);});
                google.maps.event.addListener(m_Map,   'mousedown', function(event) {mouseDown(event);});
                google.maps.event.addListener(m_Map,   'mouseup',   function(event) {mouseUp(event);});

                // Register all keys to cancel rectangle selection as the escape key is unreliable for Chrome
                document.onkeypress = function(e) {
                    enableSelection(false);
                };
*/
            });

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

                    inputCount = 9999999;

                    clearMap(true);
                    m_LastTimestamp = 0;
                    updateTrack(function(locationData) {
                        if (locationData && (!locationData['locations'] || locationData['locations'].length == 0)) {
                            toastr.error("No locations found in the selected area");
                        }
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

            function updateTrack(resultHandler) {
                downloadUrl(getLocationURL(m_LastTimestamp + 1), function(data, statusCode) {
                    if (statusCode == 200) {
                        var locationData = JSON.parse(data.responseText);

                        if (resultHandler) {
                            resultHandler(locationData);
                        }

                        if (locationData['locations'] && locationData['locations'].length > 0) {
                            var locations = new Array();

                            // If we haven't gotten any points yet, configure the view to show them.
                            // If we already have at least one point, don't mess with the user by moving the view.
                            if (m_MarkerArray.length == 0) {
                                var userID = locationData['userID']
                                var bounds = locationData['bounds'];

                                if (userID) {
                                    document.title = userID + "'s Location";
                                }

                                if (bounds) {
                                    m_Map.fitBounds(new L.LatLngBounds(new L.LatLng(bounds['southWest']['lat_deg'], bounds['southWest']['lon_deg']),
                                                                       new L.LatLng(bounds['northEast']['lat_deg'], bounds['northEast']['lon_deg'])));
                                }
                            } else {
                                toastr.info("Location updated");
                            }

                            // Pull the oldest pre-existing markers in order until we get to the count we're supposed to preserve given the count of the new locations
                            var lengthToRemove = m_MarkerArray.length + locationData['locations'].length - inputCount;
                            if (lengthToRemove > 0) {
                                for (var i = 0; i < lengthToRemove && (i < m_MarkerArray.length); i++) {
                                    m_Map.removeLayer(m_MarkerArray[i]);
                                }

                                m_MarkerArray = m_MarkerArray.slice(lengthToRemove);
                                m_LatLngArray = m_LatLngArray.slice(lengthToRemove);
                            }

                            // Pull of the most recent marker from before this update.  It has the ending icon and we want to remove that.
                            if (m_MarkerArray.length > 0) {
                                var lastMarker = m_MarkerArray.pop();
                                m_Map.removeLayer(lastMarker);
                                m_LatLngArray.pop();
                                locations.push(lastMarker.get("locationData"));
                            }

                            locations = locations.concat(locationData['locations']);
                            addLocationsToMap(locations);
                        }

                        fadeStatusIndicator("statusCircle", "Active", 200, function () {fadeStatusIndicator("statusCircle", "Inactive", m_UpdateIntervalMs, null);});
                    }
                });
            }

            function addLocationsToMap(locations) {
                if (locations) {
                    for (var i = 0; i < locations.length; i++) {
                        var marker;

                        if (i == locations.length - 1) {
                            marker = addMarker(locations[i], null);
                            m_LastTimestamp = locations[i]['locationTimestamp'];
                        } else {
                            marker = addMarker(locations[i], locations[i+1]);
                        }
                    }
                }

                m_Path.setPath(m_LatLngArray);
            }

            function addMarker(location, nextLocation) {
                var marker;
                var point = new L.LatLng(location['lat_deg'], location['lon_deg']);
                var timestamp = new Date(location['locationTimestamp']);
                var html = "<font class=\"markerInfoCategory\">Time:</font> <font class=\"markerInfoValue\">" + " " + hours24ToHours12(timestamp.getHours()) + ":" + padNumber(timestamp.getMinutes(), 2) + 
                               ":" + padNumber(timestamp.getSeconds(), 2) + " " + (timestamp.getHours() > 11 ? "PM" : "AM") + " " +
                               " " + (timestamp.getMonth() + 1) + "/" + timestamp.getDate() + "/" + timestamp.getFullYear() + "</font><br/>";
                location['accuracy_m'] = ('accuracy_m' in location) ? location['accuracy_m'] : 1000;
                html += "<font class=\"markerInfoCategory\">Accuracy:</font> <font class=\"markerInfoValue\">" + Math.round((location['accuracy_m'] * 10) / 10.0) + " m</font><br/>";

                if (location['speed_mps'] != null) {
                    html += "<font class=\"markerInfoCategory\">Speed:</font> <font class=\"markerInfoValue\">" + ((location['speed_mps'] * 3600) / 1609.34).toFixed(1) + " mph</font><br/>";
                }

                if (location['alt_m'] != null) {
                    html += "<font class=\"markerInfoCategory\">Altitude:</font> <font class=\"markerInfoValue\">" + (location['alt_m'] * 3.28084).toFixed(0) + " feet</font><br/>";
                }

                if (location['battery_percent'] != null) {
                    html += "<font class=\"markerInfoCategory\">Battery:</font> <font class=\"markerInfoValue\">" + location['battery_percent'].toFixed(0) + "%</font><br/>";
                }

                html += "<font class=\"markerInfoCategory\">Location Source:</font> <font class=\"markerInfoValue\">" + location['provider'] + "</font><br/>";

                if (nextLocation == null) {
                    marker = addFinalMarker(m_Map, point, m_MarkerArray.length + 10);
                    //m_AccuracyCircle.setCenter(point);
                    //m_AccuracyCircle.setRadius(location['accuracy_m']);
                } else {
                    var nextPoint = new L.LatLng(nextLocation['lat_deg'], nextLocation['lon_deg']);

                    html += "<br/>";

                    var bearingDegrees = getBearingDegrees(point, nextPoint);
                    html += "<font class=\"markerInfoCategory\">Calculated bearing:</font> <font class=\"markerInfoValue\">" + Math.round(bearingDegrees) + "&deg;</font><br/>";
/*
                    var distanceMeters = google.maps.geometry.spherical.computeDistanceBetween(point, nextPoint);
                    if (distanceMeters > 1000) {
                        html += "<font class=\"markerInfoCategory\">Distance to next:</font> <font class=\"markerInfoValue\">" + (distanceMeters / 1000).toFixed(2) + " km</font><br/>";
                    } else {
                        html += "<font class=\"markerInfoCategory\">Distance to next:</font> <font class=\"markerInfoValue\">" + distanceMeters.toFixed(1) + " m</font><br/>";
                    }

                    var speedToNextMph = ((distanceMeters / ((nextLocation['locationTimestamp'] - location['locationTimestamp']) / 1000) * 3600) / 1609.34).toFixed(1)
                    html += "<font class=\"markerInfoCategory\">Calculated speed to next:</font> <font class=\"markerInfoValue\">" + speedToNextMph + " mph</font><br/>";
*/
                    marker = addStandardMarker(m_Map, point, bearingDegrees, m_MarkerArray.length + 10);
                }

                //bindMetadata(marker, html, location);

                m_MarkerArray.push(marker);
                m_LatLngArray.push(point);

                return marker;
            }

            function addStandardMarker(map, point, bearingDegrees, zIndex) {
                var direction = getDirection(bearingDegrees);
/*
                var image = new google.maps.MarkerImage("markers/small/red/" + direction + ".png",
                                                        new google.maps.Size(13, 20),  // Size
                                                        new google.maps.Point(0,0),    // Origin
                                                        new google.maps.Point(6, 20)); // Anchor

                var shadow = new google.maps.MarkerImage("markers/small/shadow.png",
                                                         new google.maps.Size(22, 20),  // Size
                                                         new google.maps.Point(0,0),    // Origin
                                                         new google.maps.Point(6, 20)); // Anchor
                var clickable = { coord: [-1, 0, -1, 13, 11, 13, 11, 0], type: 'poly'};
*/
                var marker = new L.Marker(point).addTo(map);

                return marker;
            }

            function addFinalMarker(map, point, zIndex) {
/*
                var image = new google.maps.MarkerImage("markers/large/red/final.png",
                                                        new google.maps.Size(20, 32),  // Size
                                                        new google.maps.Point(0,0),    // Origin
                                                        new google.maps.Point(10, 32)); // Anchor

                var shadow = new google.maps.MarkerImage("markers/large/shadow.png",
                                                         new google.maps.Size(37, 34),  // Size
                                                         new google.maps.Point(0,0),    // Origin
                                                         new google.maps.Point(10, 34)); // Anchor
                var clickable = { coord: [-1, 0, -1, 24, 19, 24, 19, 0], type: 'poly'};
*/
                var marker = new L.Marker(point).addTo(map);

                return marker;
            }

            function bindMetadata(marker, html, locationData) {
                marker.set("infoHTML", html);
                marker.set("locationData", locationData);

                google.maps.event.addListener(marker, 'click', function() {
                        m_InfoWindow.setContent(marker.get("infoHTML"));
                        m_InfoWindow.open(m_Map, marker);
                        m_AccuracyCircle.setCenter(marker.getPosition());
                        m_AccuracyCircle.setMap(m_Map);
                        m_AccuracyCircle.setRadius(marker.get("locationData")['accuracy_m']);
                });
            }

            function clearMap(removeLastPoint) {
                if (m_MarkerArray.length > 0) {
                    var lastMarker = m_MarkerArray[m_MarkerArray.length-1];

                    for (var i = 0; i < m_MarkerArray.length; i++) {
                        m_MarkerArray[i].setMap(null);
                    }

                    m_MarkerArray = new Array();
                    m_LatLngArray = new Array();

                    m_InfoWindow.close();
                    m_AccuracyCircle.setMap(null);

                    if (!removeLastPoint) {
                        addMarker(lastMarker.get("locationData"), null);
                    }
                    m_Path.setPath(m_LatLngArray);
                }
            }

            function togglePath() {
                if (m_Path.getMap() == m_Map) {
                    m_Path.setMap(null);
                } else {
                    m_Path.setMap(m_Map);
                }
            }

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
                var dLon = (point1.lng * Math.PI / 180) - (point2.lng * Math.PI / 180);
                var lat1 = (point1.lat * Math.PI / 180);
                var lat2 = (point2.lat * Math.PI / 180);

                var y = Math.sin(dLon) * Math.cos(lat2);
                var x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
                var brng = Math.atan2(y, x);

                return 360 - (((brng * 180 / Math.PI) + 360) % 360); // Convert to degrees and normalize to [0-360)
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

            // Start - Status fade routines
            /////////////////////////////////////////////
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
            // End - Status fade routines
            /////////////////////////////////////////////

            function doNothing() {}

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

        </script>
        <script type="text/javascript" src="lib/json2.js"></script>
        <script type="text/javascript" src="lib/disableSelection.js"></script>
        <script type="text/javascript" src="lib/toastr/toastr.compressed.js"></script>
        <script type="text/javascript" src="lib/impromptu/jquery-impromptu.compressed.js"></script>
    </head>
    <body>
        <div id="map_canvas" style="width:100%; height:100%"></div>
        <div id="menuBase" style="visibility:hidden">
            <div id="menuExpandButton">
                <img src="img/ic_action_settings.png" class="center" width="32" height="32" alt="Settings" title="Settings">
            </div>
            <div class="hr"></div>
            <div class="menuItemBase" id="downloadLinks">
                <p class="menuItemParagraph">
                    Download<br>
                    <a href="javascript:downloadGpsTrack(false, true);">GPX</a> <a href="javascript:showKmlPrompt();">KML</a>
                 </p>
            </div>
            <div class="hr"></div>
            <div class="menuItemBase">
                <p class="menuItemParagraph">
                    Time to Home
                </p>
                <p id="TimeToHomeValues" class="menuItemParagraph">
                    Distance: Unknown <br>
                    Duration: Unknown
                </p>
                <p class="menuItemParagraph">
                    <a href="javascript:routeToHome();">Update</a>
                </p>
            </div>
            <div class="hr"></div>
            <div class="menuItemBase">
                <p class="menuItemParagraph">
                    <a href="javascript:enableSelection(true);">Select Region</a>
                 </p>
            </div>
            <div class="hr"></div>
            <div id="menuFooter"></div>
        </div>
        <div id="over_map_upperLeft">
            <div id="statusCircle" title="Update Status"></div>
        </div>
    </body>
</html>