(function() {
    'use strict';

    // Initialize maps when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllMaps);
    } else {
        initAllMaps();
    }

    function initAllMaps() {
        // Find all map config objects
        for (var key in window) {
            if (key.indexOf('mapmetrics_map') === 0) {
                var config = window[key];
                if (config && config.id) {
                    console.log('Found map config:', key, config);
                    initMap(config);
                }
            }
        }
    }

    function initMap(config) {
        console.log('Initializing map:', config.id);

        if (typeof mapmetricsgl === 'undefined') {
            console.error('MapMetrics GL not loaded');
            return;
        }

        var container = document.getElementById(config.id);
        if (!container) {
            console.error('Map container not found:', config.id);
            return;
        }

        try {
            var map = new mapmetricsgl.Map({
                container: config.id,
                style: config.style,
                zoom: config.zoom,
                center: [config.lng, config.lat]
            });

            map.addControl(new mapmetricsgl.NavigationControl(), 'top-right');

            if (config.marker) {
                new mapmetricsgl.Marker()
                    .setLngLat([config.lng, config.lat])
                    .addTo(map);
            }

            map.on('load', function() {
                console.log('Map loaded successfully');
            });

            map.on('error', function(e) {
                console.error('Map error:', e.error?.message || 'Unknown error');
            });

        } catch (error) {
            console.error('Error initializing map:', error);
        }
    }
})();
