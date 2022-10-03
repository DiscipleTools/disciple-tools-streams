<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Streams_Map extends DT_Magic_Url_Base
{
    public $magic = false;
    public $parts = false;
    public $page_title = 'Streams Map';
    public $root = 'streams_app';
    public $type = 'public_map';
    public $type_name = 'Streams Map';
    public static $token = 'streams_app_public_map';
    public $post_type = 'streams'; // This can be supplied or not supplied. It does not influence the url verification.

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        parent::__construct();

        $url = dt_get_url_path();
        if ( ( $this->root . '/' . $this->type ) === $url ) {

            $this->magic = new DT_Magic_URL( $this->root );
            $this->parts = $this->magic->parse_url_parts();

            // register url and access
            add_action( 'template_redirect', [ $this, 'theme_redirect' ] );
            add_filter( 'dt_blank_access', function (){ return true;
            }, 100, 1 );
            add_filter( 'dt_allow_non_login_access', function (){ return true;
            }, 100, 1 );
            add_filter( 'dt_override_header_meta', function (){ return true;
            }, 100, 1 );

            // header content
            add_filter( 'dt_blank_title', [ $this, 'page_tab_title' ] ); // adds basic title to browser tab
            add_action( 'wp_print_scripts', [ $this, 'print_scripts' ], 1500 ); // authorizes scripts
            add_action( 'wp_print_styles', [ $this, 'print_styles' ], 1500 ); // authorizes styles

            // page content
            add_action( 'dt_blank_head', [ $this, '_header' ] );
            add_action( 'dt_blank_footer', [ $this, '_footer' ] );
            add_action( 'dt_blank_body', [ $this, 'body' ] ); // body for no post key

            add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
            add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
            add_action( 'wp_enqueue_scripts', [ $this, '_wp_enqueue_scripts' ], 100 );
        }

        if ( dt_is_rest() ) {
            add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );
            add_filter( 'dt_allow_rest_access', [ $this, 'authorize_url' ], 10, 1 );
        }
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'jquery-touch-punch';
        $allowed_js[] = 'mapbox-gl';
        $allowed_js[] = 'jquery-cookie';
        $allowed_js[] = 'mapbox-cookie';
        $allowed_js[] = 'heatmap-js';
        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        $allowed_css[] = 'mapbox-gl-css';
        $allowed_css[] = 'introjs-css';
        $allowed_css[] = 'heatmap-css';
        $allowed_css[] = 'site-css';
        return $allowed_css;
    }

    public function header_javascript(){
        ?>
        <script>
            let jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'ipstack' => DT_Ipstack_API::get_key(),
                'mirror_url' => dt_get_location_grid_mirror( true ),
                'root' => esc_url_raw( rest_url() ),
                'years' => $this->get_years(),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'translations' => [
                    'add' => __( 'Add Magic', 'disciple-tools-plugin-starter-template' ),
                ],
            ]) ?>][0]

            // jQuery(document).ready(function(){
            //     window.load_map()
            // })


            window.load_map = () => {
                let spinner = jQuery('.loading-spinner')

                /* set vertical size the form column*/
                jQuery('#custom-style').append(`
                  <style>
                      #map-wrapper {
                          height: ${window.innerHeight - 350}px !important;
                      }
                      #map {
                          height: ${window.innerHeight - 350}px !important;
                      }
                  </style>`)



                window.get_geojson().then(function(data){

                    mapboxgl.accessToken = jsObject.map_key;
                    var map = new mapboxgl.Map({
                        container: 'map',
                        style: 'mapbox://styles/mapbox/light-v10',
                        center: [0, 30],
                        minZoom: 0,
                        zoom: 1
                    });

                    map.dragRotate.disable();
                    map.touchZoomRotate.disableRotation();

                    map.on('load', function() {
                        map.addSource('layer-source', {
                            type: 'geojson',
                            data: data
                        });

                        map.addLayer({
                            id: 'circle-layer',
                            type: 'circle',
                            source: 'layer-source',
                            paint: {
                                'circle-color': '#00d9ff',
                                'circle-radius':12,
                                'circle-stroke-width': 1,
                                'circle-stroke-color': '#fff'
                            }
                        });

                       // @see https://docs.mapbox.com for all the capacity of mapbox mapping.
                        spinner.removeClass('active')

                        var bounds = new mapboxgl.LngLatBounds();
                        data.features.forEach(function(feature) {
                            bounds.extend(feature.geometry.coordinates);
                        });
                        map.fitBounds(bounds, { padding: {top: 20, bottom:20, left: 20, right: 20 } });

                    });
                })
            }

            window.get_geojson = ( time_start, time_end ) => {
                return jQuery.ajax({
                    type: "POST",
                    data: JSON.stringify({ action: 'geojson', parts: jsObject.parts, time_start: time_start, time_end: time_end }),
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                    }
                })
                    .fail(function(e) {
                        console.log(e)
                        jQuery('#error').html(e)
                    })
            }

        </script>
        <?php
    }

    public function header_style() {
        ?>
        <style>
            body {
                background: white !important;
            }
            #initialize-screen {
                width: 100%;
                height: 2000px;
                z-index: 100;
                background-color: white;
                position: absolute;
            }
            #initialize-spinner-wrapper{
                position:relative;
                top:45%;
            }
            progress {
                top: 50%;
                margin: 0 auto;
                height:50px;
                width:300px;
            }
        </style>
        <?php
    }

    public function footer_javascript(){
    }

    public function body(){
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <style>
            #wrapper {
                width: 100% !important;
                max-width: 100% !important;
            }
            #content {
                width: 100% !important;
                max-width: 100% !important;
            }
            #top_bar {
                position:absolute;
                top:0;
                z-index: 10;
                background-color:white;
                opacity: .9;
                width:100%;
            }
        </style>
        <style id="custom-style"></style>
        <div id="wrapper">
            <div class="grid-x" id="top_bar">
                <div class="cell center" id="title"></div>
                <div class="cell center">
                    <button class="button-small button" style="background-color: royalblue;margin-top:10px;" id="baptisms">Baptisms</button>
                    <button class="button-small button" style="background-color: orange;margin-top:10px;" id="disciples">Disciples</button>
                    <button class="button-small button" style="background-color: green;margin-top:10px;" id="churches">Churches</button>
                    <button class="button-small button hollow" style="margin-top:10px;" id="all">All</button>
                    <select style="width:150px;" id="year_filter"></select>
                </div>
            </div>
            <div class="grid-x grid-padding-x">
                <div class="cell center" id="bottom-spinner"><span class="loading-spinner active"></span></div>
                <div class="cell" id="content">
                    <div id="map-wrapper">
                        <div id='map'></div>
                    </div>
                </div>
                <div class="cell grid" id="error"></div>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($) {
                clearInterval(window.fiveMinuteTimer)

                let d = new Date()
                let n = d.getFullYear()
                let e = n - 11
                let ten_years = ''
                for(var i = n; i>=e; i--){
                    ten_years += `<option value="${window.lodash.escape( i )}">${window.lodash.escape( i )}</option>`.toString()
                }
                let year_filter = jQuery('#year_filter')
                year_filter.append(ten_years)


                /* LOAD */
                let spinner = $('.loading-spinner')
                let title = $('#title')
                title.html( jsObject.translations.title )

                /* set vertical size the form column*/
                $('#custom-style').append(`
                    <style>
                        #wrapper {
                            height: ${window.innerHeight}px !important;
                        }
                        #map-wrapper {
                            height: ${window.innerHeight}px !important;
                        }
                        #map {
                            height: ${window.innerHeight}px !important;
                        }
                    </style>`)

                window.get_geojson().then(function (data) {
                    mapboxgl.accessToken = jsObject.map_key;
                    var map = new mapboxgl.Map({
                        container: 'map',
                        style: 'mapbox://styles/mapbox/light-v10',
                        center: [-98, 38.88],
                        minZoom: 1,
                        maxZoom: 14,
                        zoom: 3
                    });

                    map.dragRotate.disable();
                    map.touchZoomRotate.disableRotation();

                    map.on('load', function () {
                        window.build_layers(map, data, jQuery('#year_filter').val())

                        year_filter.on('change', function(e){
                            const layers = ['layer-baptisms-circle', 'layer-baptisms-count', 'layer-disciples-circle', 'layer-disciples-count','layer-churches-circle', 'layer-churches-count' ]
                            let layer_var = 'churches'
                            for( const layer_id of layers) {
                                if ( layer_id.search('churches') !== -1 ) {
                                    layer_var = 'churches'
                                }
                                else if ( layer_id.search('disciples') !== -1 ) {
                                    layer_var = 'disciples'
                                }
                                else {
                                    layer_var = 'baptisms'
                                }
                                map.setFilter(layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", jQuery(this).val(), ['get', 'year']] ]);
                            }
                        })
                    });
                })


                window.build_layers = ( map, data, year ) => {
                    map.addSource('layer-source-reports', {
                        type: 'geojson',
                        data: data,
                        cluster: false,
                        clusterMaxZoom: 1,
                        clusterRadius: 1
                    });

                    map.addLayer({
                        id: 'layer-churches-circle',
                        type: 'circle',
                        source: 'layer-source-reports',
                        paint: {
                            'circle-color': 'green',
                            'circle-radius': {
                                stops: [[8, 24], [11, 29], [16, 35]]
                            },
                            'circle-stroke-width': 0.2,
                            'circle-stroke-color': '#fff'
                        },
                        filter: [ "all", ['==', 'churches', ['get', 'type'] ], ["==", year, ['get', 'year']] ]
                    });
                    map.addLayer({
                        id: 'layer-churches-count',
                        type: 'symbol',
                        source: 'layer-source-reports',
                        layout: {
                            "text-field": ['get', 'value'],
                            "icon-allow-overlap": true
                        },
                        paint: {
                            "text-color": "#ffffff"
                        },
                        filter: [ "all", ['==', 'churches', ['get', 'type'] ], ["==", year, ['get', 'year']] ]
                    });

                    /* disciples */
                    map.addLayer({
                        id: 'layer-disciples-circle',
                        type: 'circle',
                        source: 'layer-source-reports',
                        paint: {
                            'circle-color': 'orange',
                            'circle-radius': {
                                stops: [[8, 20], [11, 25], [16, 28]]
                            },
                            'circle-stroke-width': 0.2,
                            'circle-stroke-color': '#fff'
                        },
                        filter: [ "all", ['==', 'disciples', ['get', 'type'] ], ["==", year, ['get', 'year']] ]
                    });
                    map.addLayer({
                        id: 'layer-disciples-count',
                        type: 'symbol',
                        source: 'layer-source-reports',
                        layout: {
                            "text-field": ['get', 'value'],
                            "icon-allow-overlap": true
                        },
                        paint: {
                            "text-color": "#ffffff"
                        },
                        filter: [ "all", ['==', 'disciples', ['get', 'type'] ], ["==", year, ['get', 'year']] ]
                    });

                    /* baptism */
                    map.addLayer({
                        id: 'layer-baptisms-circle',
                        type: 'circle',
                        source: 'layer-source-reports',
                        paint: {
                            'circle-color': 'royalblue',
                            'circle-radius': {
                                stops: [[8, 16], [11, 20], [16, 23]]
                            },
                            'circle-stroke-width': 0.5,
                            'circle-stroke-color': '#fff'
                        },
                        filter: [ "all", ['==', 'baptisms', ['get', 'type'] ], ["==", year, ['get', 'year']] ]
                    });
                    map.addLayer({
                        id: 'layer-baptisms-count',
                        type: 'symbol',
                        source: 'layer-source-reports',
                        layout: {
                            "text-field": ['get', 'value'],
                            "icon-allow-overlap": true
                        },
                        paint: {
                            "text-color": "#ffffff"
                        },
                        filter: [ "all", ['==', 'baptisms', ['get', 'type'] ], ["==", year, ['get', 'year']] ]
                    });

                    map.setLayoutProperty('layer-baptisms-count', 'visibility', 'none');
                    map.setLayoutProperty('layer-disciples-count', 'visibility', 'none');
                    map.setLayoutProperty('layer-churches-count', 'visibility', 'none');
                    spinner.removeClass('active')

                    var bounds = new mapboxgl.LngLatBounds();
                    data.features.forEach(function(feature) {
                        bounds.extend(feature.geometry.coordinates);
                    });
                    map.fitBounds(bounds, {padding: 100});
                    // end set bounds

                    jQuery('#baptisms').on('click', () => {
                        window.hide_all(map)
                        map.setLayoutProperty('layer-baptisms-circle', 'visibility', 'visible');
                        map.setLayoutProperty('layer-baptisms-count', 'visibility', 'visible');
                    })
                    jQuery('#disciples').on('click', () => {
                        window.hide_all(map)
                        map.setLayoutProperty('layer-disciples-circle', 'visibility', 'visible');
                        map.setLayoutProperty('layer-disciples-count', 'visibility', 'visible');
                    })
                    jQuery('#churches').on('click', () => {
                        window.hide_all(map)
                        map.setLayoutProperty('layer-churches-circle', 'visibility', 'visible');
                        map.setLayoutProperty('layer-churches-count', 'visibility', 'visible');
                    })
                    jQuery('#all').on('click', () => {
                        window.show_all( map )
                    })
                }
                window.hide_all = ( map ) => {
                    const layers = ['layer-baptisms-circle', 'layer-baptisms-count', 'layer-disciples-circle', 'layer-disciples-count','layer-churches-circle', 'layer-churches-count' ]
                    for( const layer_id of layers) {
                        map.setLayoutProperty( layer_id, 'visibility', 'none');
                    }
                }
                window.show_all = ( map ) => {
                    window.hide_all( map )
                    const layers = ['layer-baptisms-circle', 'layer-disciples-circle', 'layer-churches-circle' ]
                    for( const layer_id of layers) {
                        map.setLayoutProperty( layer_id, 'visibility', 'visible');
                    }
                }

            })
        </script>
        <?php
    }

    public static function _wp_enqueue_scripts(){
        DT_Mapbox_API::load_mapbox_header_scripts();
    }

    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_endpoints() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace,
            '/'.$this->type,
            [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint' ],
                ],
            ]
        );
    }

    public function endpoint( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );

        switch ( $params['action'] ) {
            case 'geojson':
                return $this->endpoint_geojson( $params['parts'], $params['time_start'] ?? null, $params['time_end'] ?? null );
            default:
                return new WP_Error( __METHOD__, 'Missing valid action parameters', [ 'status' => 400 ] );
        }
    }

    public function endpoint_geojson( $parts, $time_start = null, $time_end = null ) {
        global $wpdb;
        dt_write_log(__METHOD__);

        if ( empty( $time_start ) ) {
            $time_start = strtotime( 'January 1, ' . date('Y' ) );
            dt_write_log($time_start);
        }
        if ( empty( $time_end ) ) {
            $time_end = strtotime( 'December 31,, ' . date('Y' ) . ' 23:59:59' );
            dt_write_log($time_end);
        }

        $results = $wpdb->get_results(
        "SELECT * 
                FROM $wpdb->dt_reports 
                WHERE type = 'streams_app' 
                  AND subtype = 'report' 
                  ;" , ARRAY_A );

        if ( empty( $results ) ) {
            return $this->_empty_geojson();
        }

        $features = [];
        foreach ( $results as $result ) {
            $payload = maybe_unserialize( $result['payload'] );
            $time = $result['time_end'];
            if ( empty( $time ) ) {
                $time = $result['time_begin'];
            }
            if ( empty( $time ) ) {
                continue;
            }
            $year = gmdate( 'Y', $time );
            $features[] = array(
                'type' => 'Feature',
                'properties' => array(
                    'value' => $result['value'],
                    'type' => $payload['type'] ?? '',
                    'year' => $year,
                    'label' => $result['label'],
                    'grid_id' => $result['grid_id']
                ),
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        (float) $result['lng'],
                        (float) $result['lat'],
                        1
                    ),
                ),
            );
        }

        $geojson = array(
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        return $geojson;
    }

    private function _empty_geojson() {
        return array(
            'type' => 'FeatureCollection',
            'features' => array()
        );
    }

    public function get_years() {
        $years = [];
        $today = date('Y' );
        dt_write_log($today);
        return $years;
    }



}
DT_Streams_Map::instance();
