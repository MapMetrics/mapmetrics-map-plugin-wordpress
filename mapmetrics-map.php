<?php
/**
 * Plugin Name: MapMetrics Map
 * Plugin URI: https://mapmetrics.com
 * Description: Embed interactive MapMetrics maps with custom styles and markers
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: mapmetrics-map
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAPMETRICS_VERSION', '2.0.0');
define('MAPMETRICS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Enqueue scripts and styles
 */
function mapmetrics_enqueue_assets() {
    wp_enqueue_style(
        'mapmetrics-gl-css',
        'https://cdn.mapmetrics-atlas.net/versions/latest/mapmetrics-gl.css',
        array(),
        MAPMETRICS_VERSION
    );

    wp_enqueue_script(
        'mapmetrics-gl-js',
        'https://cdn.mapmetrics-atlas.net/versions/latest/mapmetrics-gl.js',
        array(),
        MAPMETRICS_VERSION,
        true
    );

    wp_enqueue_style(
        'mapmetrics-custom-css',
        MAPMETRICS_PLUGIN_URL . 'assets/style.css',
        array('mapmetrics-gl-css'),
        MAPMETRICS_VERSION
    );

    wp_enqueue_script(
        'mapmetrics-custom-js',
        MAPMETRICS_PLUGIN_URL . 'assets/script.js',
        array('mapmetrics-gl-js'),
        MAPMETRICS_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'mapmetrics_enqueue_assets');

/**
 * Add settings page for MapMetrics styles
 */
function mapmetrics_add_admin_menu() {
    add_options_page(
        'MapMetrics Map Settings',
        'MapMetrics Map',
        'manage_options',
        'mapmetrics-map',
        'mapmetrics_options_page'
    );
}
add_action('admin_menu', 'mapmetrics_add_admin_menu');

/**
 * Register settings
 */
function mapmetrics_settings_init() {
    register_setting('mapmetrics', 'mapmetrics_styles');
}
add_action('admin_init', 'mapmetrics_settings_init');

/**
 * Settings page HTML
 */
function mapmetrics_options_page() {
    ?>
    <div class="wrap">
        <h1>MapMetrics Map Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('mapmetrics');
            $styles = get_option('mapmetrics_styles', array());
            ?>
            <h2>Custom Map Styles</h2>
            <p>Add your custom MapMetrics style URLs. These will be available to use in shortcodes.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">Style Name</th>
                    <td>
                        <input type="text" name="mapmetrics_styles[name][]" class="regular-text" placeholder="e.g., Dark Theme">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Style URL</th>
                    <td>
                        <input type="text" name="mapmetrics_styles[url][]" class="regular-text" placeholder="https://gateway.mapmetrics-atlas.net/styles/...">
                    </td>
                </tr>
            </table>

            <?php if (!empty($styles['name']) && is_array($styles['name'])): ?>
                <h3>Existing Styles</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Style Name</th>
                            <th>Style URL</th>
                            <th>Shortcode Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($styles['name'] as $index => $name): ?>
                            <tr>
                                <td><?php echo esc_html($name); ?></td>
                                <td><code><?php echo esc_html(substr($styles['url'][$index], 0, 80)); ?>...</code></td>
                                <td><code>style="<?php echo esc_html($name); ?>"</code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * MapMetrics shortcode
 * Usage: [mapmetrics_map]
 */
function mapmetrics_map_shortcode($atts) {
    $atts = shortcode_atts(array(
        'width' => '100%',
        'height' => '500px',
        'lng' => '2.349902',
        'lat' => '48.852966',
        'zoom' => '11',
        'bearing' => '0',
        'pitch' => '0',
        'style' => 'default',
        'marker' => 'false',
        'marker_image' => '',
        'marker_width' => '60',
        'marker_height' => '60',
        'markers_geojson' => '',
        'markers' => '',
        'polyline' => '',
        'polyline_color' => '#000000',
        'polyline_width' => '3',
        'polylines' => '',
        'attribution' => '',
        'autozoom' => 'false',
        'autozoom_start' => '2',
        'autozoom_delay' => '1000',
        'autozoom_duration' => '3000',
        'autoloop' => 'false',
        'autoloop_locations' => '',
        'autoloop_delay' => '3000',
        'autoloop_duration' => '2000',
        'id' => 'map-' . uniqid()
    ), $atts);

    // Resolve style name to URL if it's a saved style
    $style_url = $atts['style'];
    if (strpos($style_url, 'http') !== 0) {
        // It's a style name, look it up
        $saved_styles = get_option('mapmetrics_styles', array());
        if (!empty($saved_styles['name']) && is_array($saved_styles['name'])) {
            $style_index = array_search($style_url, $saved_styles['name']);
            if ($style_index !== false && isset($saved_styles['url'][$style_index])) {
                $style_url = $saved_styles['url'][$style_index];
            } else {
                // Style name not found, return error
                return '<div class="mapmetrics-error" style="padding: 20px; background: #f44336; color: white; border-radius: 4px;">MapMetrics Error: Style "' . esc_html($style_url) . '" not found. Please add it in Settings → MapMetrics Map.</div>';
            }
        } else {
            // No styles configured
            return '<div class="mapmetrics-error" style="padding: 20px; background: #f44336; color: white; border-radius: 4px;">MapMetrics Error: No styles configured. Please add a style in Settings → MapMetrics Map.</div>';
        }
    }

    $map_id = 'mapmetrics-' . uniqid();

    ob_start();
    ?>
    <div id="<?php echo esc_attr($map_id); ?>" class="mapmetrics-map" style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>; min-height: 500px;"></div>
    <script type="text/javascript">
        (function() {
            var mapId = '<?php echo esc_js($map_id); ?>';
            var mapConfig = {
                id: mapId,
                lng: <?php echo floatval($atts['lng']); ?>,
                lat: <?php echo floatval($atts['lat']); ?>,
                zoom: <?php echo intval($atts['zoom']); ?>,
                bearing: <?php echo floatval($atts['bearing']); ?>,
                pitch: <?php echo floatval($atts['pitch']); ?>,
                style: <?php echo json_encode($style_url); ?>,
                marker: <?php echo $atts['marker'] === 'true' ? 'true' : 'false'; ?>,
                markerImage: <?php echo json_encode($atts['marker_image']); ?>,
                markerWidth: <?php echo intval($atts['marker_width']); ?>,
                markerHeight: <?php echo intval($atts['marker_height']); ?>,
                markersGeoJSON: <?php echo json_encode($atts['markers_geojson']); ?>,
                markers: <?php echo json_encode($atts['markers']); ?>,
                polyline: <?php echo json_encode($atts['polyline']); ?>,
                polylineColor: <?php echo json_encode($atts['polyline_color']); ?>,
                polylineWidth: <?php echo intval($atts['polyline_width']); ?>,
                polylines: <?php echo json_encode($atts['polylines']); ?>,
                attribution: <?php echo json_encode($atts['attribution']); ?>,
                autoZoom: <?php echo $atts['autozoom'] === 'true' ? 'true' : 'false'; ?>,
                autoZoomStart: <?php echo floatval($atts['autozoom_start']); ?>,
                autoZoomDelay: <?php echo intval($atts['autozoom_delay']); ?>,
                autoZoomDuration: <?php echo intval($atts['autozoom_duration']); ?>,
                autoLoop: <?php echo $atts['autoloop'] === 'true' ? 'true' : 'false'; ?>,
                autoLoopLocations: <?php echo json_encode($atts['autoloop_locations']); ?>,
                autoLoopDelay: <?php echo intval($atts['autoloop_delay']); ?>,
                autoLoopDuration: <?php echo intval($atts['autoloop_duration']); ?>
            };

            console.log('Map config created for:', mapId);
            console.log('Config:', mapConfig);

            function startAutoLoop(map, config) {
                // Parse locations string: "lng1,lat1,zoom1|lng2,lat2,zoom2|lng3,lat3,zoom3"
                var locationsStr = config.autoLoopLocations.trim();
                if (!locationsStr) {
                    console.error('No locations provided for auto-loop');
                    return;
                }

                var locations = [];
                var parts = locationsStr.split('|');

                for (var i = 0; i < parts.length; i++) {
                    var coords = parts[i].trim().split(',');
                    if (coords.length >= 2) {
                        locations.push({
                            lng: parseFloat(coords[0]),
                            lat: parseFloat(coords[1]),
                            zoom: coords.length >= 3 ? parseFloat(coords[2]) : config.zoom
                        });
                    }
                }

                if (locations.length === 0) {
                    console.error('No valid locations parsed for auto-loop');
                    return;
                }

                console.log('Auto-loop locations:', locations);

                var currentIndex = 0;

                function flyToNext() {
                    var location = locations[currentIndex];
                    console.log('Flying to location ' + (currentIndex + 1) + ':', location);

                    map.flyTo({
                        center: [location.lng, location.lat],
                        zoom: location.zoom,
                        speed: 0.8,
                        curve: 1,
                        duration: config.autoLoopDuration,
                        essential: true
                    });

                    currentIndex = (currentIndex + 1) % locations.length;

                    setTimeout(flyToNext, config.autoLoopDelay + config.autoLoopDuration);
                }

                // Start the loop after initial delay
                setTimeout(flyToNext, config.autoLoopDelay);
            }

            function initializeMap() {
                if (typeof mapmetricsgl === 'undefined') {
                    console.log('Waiting for MapMetrics GL library...');
                    setTimeout(initializeMap, 100);
                    return;
                }

                try {
                    console.log('Initializing map:', mapId);
                    console.log('AutoZoom enabled?', mapConfig.autoZoom, 'Start zoom:', mapConfig.autoZoomStart, 'Target zoom:', mapConfig.zoom);
                    console.log('AutoLoop enabled?', mapConfig.autoLoop, 'Locations:', mapConfig.autoLoopLocations);

                    // If autoZoom is enabled, start at a zoomed-out view
                    var initialZoom = mapConfig.autoZoom ? mapConfig.autoZoomStart : mapConfig.zoom;
                    console.log('Initial zoom level:', initialZoom);

                    var map = new mapmetricsgl.Map({
                        container: mapId,
                        style: mapConfig.style,
                        zoom: initialZoom,
                        center: [mapConfig.lng, mapConfig.lat],
                        bearing: mapConfig.bearing,
                        pitch: mapConfig.pitch,
                        attributionControl: false
                    });

                    map.addControl(new mapmetricsgl.NavigationControl(), 'top-right');

                    // Add attribution control with custom text (collapsible)
                    if (mapConfig.attribution) {
                        map.addControl(new mapmetricsgl.AttributionControl({
                            customAttribution: mapConfig.attribution,
                            compact: true
                        }));
                    } else {
                        map.addControl(new mapmetricsgl.AttributionControl({
                            compact: true
                        }));
                    }

                    // Add single marker (basic or custom image)
                    if (mapConfig.marker) {
                        if (mapConfig.markerImage) {
                            // Custom image marker
                            var el = document.createElement('div');
                            el.className = 'mapmetrics-custom-marker';
                            el.style.backgroundImage = 'url(' + mapConfig.markerImage + ')';
                            el.style.backgroundSize = 'cover';
                            el.style.width = mapConfig.markerWidth + 'px';
                            el.style.height = mapConfig.markerHeight + 'px';
                            el.style.cursor = 'pointer';

                            new mapmetricsgl.Marker({element: el})
                                .setLngLat([mapConfig.lng, mapConfig.lat])
                                .addTo(map);
                        } else {
                            // Default marker
                            new mapmetricsgl.Marker()
                                .setLngLat([mapConfig.lng, mapConfig.lat])
                                .addTo(map);
                        }
                    }

                    // Add simple format markers: "lng,lat,size,image,message|lng,lat,size,image,message"
                    if (mapConfig.markers) {
                        var markersStr = mapConfig.markers.trim();
                        if (markersStr) {
                            var markersList = markersStr.split('|');
                            markersList.forEach(function(markerData) {
                                var parts = markerData.trim().split(',');
                                if (parts.length >= 2) {
                                    var lng = parseFloat(parts[0]);
                                    var lat = parseFloat(parts[1]);
                                    var size = parts.length >= 3 ? parseInt(parts[2]) : 60;
                                    var imageUrl = parts.length >= 4 && parts[3] ? parts[3] : 'https://picsum.photos/' + size + '/' + size;
                                    var message = parts.length >= 5 ? parts.slice(4).join(',') : '';

                                    var el = document.createElement('div');
                                    el.className = 'mapmetrics-simple-marker';
                                    el.style.backgroundImage = 'url(' + imageUrl + ')';
                                    el.style.backgroundSize = 'cover';
                                    el.style.width = size + 'px';
                                    el.style.height = size + 'px';
                                    el.style.cursor = 'pointer';

                                    if (message) {
                                        el.addEventListener('click', function() {
                                            alert(message);
                                        });
                                    }

                                    new mapmetricsgl.Marker({element: el})
                                        .setLngLat([lng, lat])
                                        .addTo(map);
                                }
                            });
                        }
                    }

                    // Add GeoJSON markers
                    if (mapConfig.markersGeoJSON) {
                        try {
                            var geojson = JSON.parse(mapConfig.markersGeoJSON);
                            if (geojson.features && Array.isArray(geojson.features)) {
                                geojson.features.forEach(function(marker) {
                                    var el = document.createElement('div');
                                    el.className = 'mapmetrics-geojson-marker';

                                    var iconSize = marker.properties.iconSize || [60, 60];
                                    var iconUrl = marker.properties.iconUrl || 'https://picsum.photos/' + iconSize.join('/');

                                    el.style.backgroundImage = 'url(' + iconUrl + ')';
                                    el.style.backgroundSize = 'cover';
                                    el.style.width = iconSize[0] + 'px';
                                    el.style.height = iconSize[1] + 'px';
                                    el.style.cursor = 'pointer';

                                    if (marker.properties.message) {
                                        el.addEventListener('click', function() {
                                            alert(marker.properties.message);
                                        });
                                    }

                                    new mapmetricsgl.Marker({element: el})
                                        .setLngLat(marker.geometry.coordinates)
                                        .addTo(map);
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing GeoJSON markers:', e);
                        }
                    }

                    map.on('load', function() {
                        console.log('Map loaded successfully:', mapId);
                        console.log('autoZoom:', mapConfig.autoZoom, 'autoLoop:', mapConfig.autoLoop);

                        // Add single polyline (legacy support)
                        if (mapConfig.polyline) {
                            var polylineStr = mapConfig.polyline.trim();
                            if (polylineStr) {
                                var coordinates = [];
                                var points = polylineStr.split('|');

                                points.forEach(function(point) {
                                    var coords = point.trim().split(',');
                                    if (coords.length >= 2) {
                                        coordinates.push([parseFloat(coords[0]), parseFloat(coords[1])]);
                                    }
                                });

                                if (coordinates.length >= 2) {
                                    map.addSource('polyline', {
                                        'type': 'geojson',
                                        'data': {
                                            'type': 'Feature',
                                            'properties': {},
                                            'geometry': {
                                                'type': 'LineString',
                                                'coordinates': coordinates
                                            }
                                        }
                                    });

                                    map.addLayer({
                                        'id': 'polyline',
                                        'type': 'line',
                                        'source': 'polyline',
                                        'layout': {
                                            'line-join': 'round',
                                            'line-cap': 'round'
                                        },
                                        'paint': {
                                            'line-color': mapConfig.polylineColor,
                                            'line-width': mapConfig.polylineWidth
                                        }
                                    });

                                    console.log('Polyline added with', coordinates.length, 'points');
                                }
                            }
                        }

                        // Add multiple polylines: "lng,lat|lng,lat;color;width~lng,lat|lng,lat;color;width"
                        if (mapConfig.polylines) {
                            var polylinesStr = mapConfig.polylines.trim();
                            if (polylinesStr) {
                                var polylinesList = polylinesStr.split('~');

                                polylinesList.forEach(function(polylineData, index) {
                                    var parts = polylineData.split(';');
                                    var coordsStr = parts[0];
                                    var color = parts.length >= 2 && parts[1] ? parts[1] : '#000000';
                                    var width = parts.length >= 3 && parts[2] ? parseInt(parts[2]) : 3;

                                    var coordinates = [];
                                    var points = coordsStr.split('|');

                                    points.forEach(function(point) {
                                        var coords = point.trim().split(',');
                                        if (coords.length >= 2) {
                                            coordinates.push([parseFloat(coords[0]), parseFloat(coords[1])]);
                                        }
                                    });

                                    if (coordinates.length >= 2) {
                                        var sourceId = 'polyline-' + index;
                                        var layerId = 'polyline-layer-' + index;

                                        map.addSource(sourceId, {
                                            'type': 'geojson',
                                            'data': {
                                                'type': 'Feature',
                                                'properties': {},
                                                'geometry': {
                                                    'type': 'LineString',
                                                    'coordinates': coordinates
                                                }
                                            }
                                        });

                                        map.addLayer({
                                            'id': layerId,
                                            'type': 'line',
                                            'source': sourceId,
                                            'layout': {
                                                'line-join': 'round',
                                                'line-cap': 'round'
                                            },
                                            'paint': {
                                                'line-color': color,
                                                'line-width': width
                                            }
                                        });

                                        console.log('Polyline', index + 1, 'added with', coordinates.length, 'points, color:', color, 'width:', width);
                                    }
                                });
                            }
                        }

                        // If both autozoom and autoloop are enabled, do autozoom first, then start loop
                        if (mapConfig.autoZoom && mapConfig.autoLoop) {
                            console.log('Starting auto-zoom, then auto-loop...');
                            console.log('Zoom from', mapConfig.autoZoomStart, 'to', mapConfig.zoom);
                            setTimeout(function() {
                                map.flyTo({
                                    center: [mapConfig.lng, mapConfig.lat],
                                    zoom: mapConfig.zoom,
                                    speed: 0.5,
                                    curve: 1,
                                    duration: mapConfig.autoZoomDuration,
                                    essential: true
                                });

                                // Start autoloop after autozoom completes
                                setTimeout(function() {
                                    startAutoLoop(map, mapConfig);
                                }, mapConfig.autoZoomDuration + 500);
                            }, mapConfig.autoZoomDelay);
                        }
                        // Only auto-zoom
                        else if (mapConfig.autoZoom) {
                            console.log('Starting auto-zoom animation...');
                            setTimeout(function() {
                                map.flyTo({
                                    center: [mapConfig.lng, mapConfig.lat],
                                    zoom: mapConfig.zoom,
                                    speed: 0.5,
                                    curve: 1,
                                    duration: mapConfig.autoZoomDuration,
                                    essential: true
                                });
                            }, mapConfig.autoZoomDelay);
                        }
                        // Only auto-loop
                        else if (mapConfig.autoLoop && mapConfig.autoLoopLocations) {
                            console.log('Starting auto-loop animation...');
                            startAutoLoop(map, mapConfig);
                        }
                    });

                    map.on('error', function(e) {
                        console.error('Map error:', e.error?.message || 'Unknown error');
                    });

                    // Expose map instance and flyTo function globally
                    window['mapmetrics_' + mapId.replace(/-/g, '_')] = map;

                    window['mapmetrics_flyTo_' + mapId.replace(/-/g, '_')] = function(lng, lat, zoom, bearing, pitch, options) {
                        var defaultOptions = {
                            center: [lng, lat],
                            zoom: zoom || map.getZoom(),
                            bearing: bearing !== undefined ? bearing : map.getBearing(),
                            pitch: pitch !== undefined ? pitch : map.getPitch(),
                            speed: 1.2,
                            curve: 1,
                            duration: 2000,
                            essential: true
                        };

                        var flyOptions = Object.assign({}, defaultOptions, options || {});

                        map.flyTo(flyOptions);
                        console.log('Flying to:', lng, lat, 'zoom:', flyOptions.zoom, 'bearing:', flyOptions.bearing, 'pitch:', flyOptions.pitch);
                    };

                } catch (error) {
                    console.error('Error initializing map:', error);
                }
            }

            // Start initialization
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeMap);
            } else {
                initializeMap();
            }
        })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('mapmetrics_map', 'mapmetrics_map_shortcode');
