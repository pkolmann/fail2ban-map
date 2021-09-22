<!DOCTYPE html>
<html lang="en-UK">
<head>
    <title>fail2ban map view</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="leaflet/leaflet.css">
    <link rel="stylesheet" href="js/spin.css">
    <script src="leaflet/leaflet.js"></script> <!-- use "leaflet/leaflet-src.js" for debug -->
    <script src="leaflet/leaflet.ajax.min.js"></script>
    <script src="leaflet/leaflet-hash.js"></script>
    <script src="leaflet/svg-icon.js"></script>
    <script src="js/spin.umd.js"></script>
    <script src="js/corslite.js"></script>

    <link rel="stylesheet" href="leaflet/leaflet-legend.css">
    <script src="leaflet/leaflet-legend.js"></script>

    <style>
        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            padding: 0;
        }

        #mapid {
            height: 100%;
            width: 100vw;
        }

        .leaflet-fade-anim .leaflet-tile, .leaflet-zoom-anim .leaflet-zoom-animated {
            will-change: auto !important;
        }

        .leaflet-control-reload {
            padding: 6px 6px 6px 6px;
        }

        .leaflet-control-reload-toggle {
            height: 1.8em;
            width: 1.8em;
        }
    </style>

</head>
<body>

<div id="mapid"></div>
<script>
    const getParams = {};
    // https://mokole.com/palette.html
    const colors = [
        '#00008b',
        '#ff8c00',
        '#008000',
        '#ffff00',
        '#7f0000',
        '#00ff00',
        '#2f4f4f',
        '#00ffff',
        '#ff00ff',
        '#1e90ff',
        '#ff69b4',
        '#ffe4c4'
    ];
    let layerControl;
    let overlays = [];
    let timeFilter = 100;

	function onEachFeature(feature, layer) {
		let out = [];
        for (const key in feature.properties) {
            if (key == 'timestamp') { continue; }
            out.push("<b>"+key+"</b>: "+feature.properties[key]);
        }

		layer.bindPopup('<p>'+out.join("<br />")+'</p>');
	}

    function filterMarkers() {
        for (let i = 0; i < overlays.length; i++) {
            overlays[i].eachLayer(function(layer) {
                if (layer.hasOwnProperty('feature') &&
                    layer['feature'].hasOwnProperty('properties') &&
                    layer['feature']['properties'].hasOwnProperty('timestamp')
                ) {
                    let dateDiff = ((new Date().getTime() / 1000) - layer['feature']['properties']['timestamp']) / 86400;
                    if (dateDiff > timeFilter) {
                        layer.getElement().style.display = "none";
                    } else {
                        layer.getElement().style.display = "";
                    }
                }
            });
        }
    }

    // check for limiters
    const params = location.search.substring(1).split('&');
    for (let i = 0; i < params.length; i++) {
        const pair = params[i].split('=');
        getParams[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
    }
    
    // https://leaflet-extras.github.io/leaflet-providers/preview/
    const osm = L.tileLayer('https://{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    map = L.map('mapid', {
        center: [47.5, 13.4],
        zoom: 8,
        layers: [osm]
    });

    const llMapLayers = {
        "Open Street Map": osm
    };

    const hashLayer = new L.Hash(map);

    if (!getParams.hasOwnProperty('day') && !getParams.hasOwnProperty('time1') && !getParams.hasOwnProperty('time2')) {
        const dateControl = L.control({position: 'topright'});
        dateControl.onAdd = function (map) {
            const div = L.DomUtil.create('div', 'leaflet-control-layers leaflet-control-layers-expanded');
            div.id = 'rangefilterdiv';
            let str = '';
            str = str+'<label><div><input name="orgSpan" class="leaflet-control-layers-selector" type="radio" value="1">';
            str = str+'<span>Last 24 hours</span></div></label>';
            str = str+'<label><div><input name="orgSpan" class="leaflet-control-layers-selector" type="radio" value="3">';
            str = str+'<span>Last 72 hours</span></div></label>';
            str = str+'<label><div><input name="orgSpan" class="leaflet-control-layers-selector" type="radio" value="7">';
            str = str+'<span>Last week</span></div></label>';
            str = str+'<label><div><input name="orgSpan" class="leaflet-control-layers-selector" type="radio" value="14">';
            str = str+'<span>Last 14 days</span></div></label>';
            str = str+'<label><div><input name="orgSpan" class="leaflet-control-layers-selector" type="radio" value="32" checked>';
            str = str+'<span>Last month</span></div></label>';
            div.innerHTML  = str;
            div.firstChild.onmousedown = div.firstChild.ondblclick = L.DomEvent.stopPropagation;
            L.DomEvent.on(div, 'change', function (e) {
                timeFilter = parseInt(e.target.value);
                filterMarkers();
            });
            return div;
        };
        dateControl.addTo(map);
    }
    let Position = L.Control.extend({
        _container: null,
        options: {
            position: 'bottomright'
        },

        onAdd: function (map) {
            const latlng = L.DomUtil.create('div', 'leaflet-control-layers leaflet-control-layers-expanded');
            this._latlng = latlng;
            return latlng;
        },

        updateHTML: function(lat, lng) {
            //this._latlng.innerHTML = "Latitude: " + lat + "   Longitiude: " + lng;
            this._latlng.innerHTML = "LatLng: " + lat + " " + lng;
        }
    });
    const position = new Position();
    map.addControl(position);

    map.addEventListener('mousemove', (event) => {
        let lat = Math.round(event.latlng.lat * 100000) / 100000;
        let lng = Math.round(event.latlng.lng * 100000) / 100000;
        position.updateHTML(lat, lng);
    });


    let serverURL = "API/getServer/";
    let urlDelimiter = '?';
    if (getParams.hasOwnProperty('day')) {
        serverURL += urlDelimiter + "day=";
        serverURL += encodeURIComponent(getParams['day']);
        urlDelimiter = '&';
    }
    if (getParams.hasOwnProperty('time1') && getParams.hasOwnProperty('time2')) {
        serverURL += urlDelimiter + "time1=";
        serverURL += encodeURIComponent(getParams['time1']);
        urlDelimiter = '&';
        serverURL += urlDelimiter + "time2=";
        serverURL += encodeURIComponent(getParams['time2']);
        urlDelimiter = '&';
    }

    corslite(serverURL, function (err, resp) {
        serverJson = JSON.parse(resp.response);

        var legendItems = [];
        for (const [key, serverName] of Object.entries(serverJson)) {
            console.log(key+": "+serverName);
            legendItems.push({
                color: colors[key % colors.length],
                label: serverName
            });


            corslite("API/getLog/?id="+key, function(err, resp) {
				const json = JSON.parse(resp.response);
                const canvasLayer = L.geoJson(json, {
					onEachFeature: onEachFeature,

                    pointToLayer: function (geoJsonPoint, latlng) {
                        var markerColor = colors[key % colors.length];

						return L.circleMarker(latlng, {
							radius: 8,
							fillColor: markerColor,
							color: "#000",
							weight: 1,
							opacity: 1,
							fillOpacity: 0.8
						});
					}
                });
                canvasLayer.addTo(map);
                layerControl.addOverlay(canvasLayer, serverName);
                overlays.push(canvasLayer);
            });
        }

        L.control.legend({
            items: legendItems,
            collapsed: true,
            buttonHtml: 'Legende'
        }).addTo(map);
    }, true);

    layerControl = L.control.layers(llMapLayers, {}).addTo(map);
    L.control.scale({imperial: false}).addTo(map);
    map.on('overlayadd', function(layer){
        filterMarkers();
    });

</script>

</body>
</html>


