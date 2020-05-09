<?php

add_action( 'after_setup_theme', 'dt_streams_metrics', 100 );
function dt_streams_metrics() {
    DT_Streams_Metrics::instance();
}

class DT_Streams_Metrics
{
    public $permissions = [ 'view_any_contacts', 'view_project_metrics' ];

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        if ( ! DT_Mapbox_API::get_key() ) {
            return;
        }
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        $url_path = dt_get_url_path();

        if ( !$this->has_permission() ){
            return;
        }

        if ( 'metrics' === substr( $url_path, '0', 7 ) ) {

            add_filter( 'dt_templates_for_urls', [ $this, 'add_url' ] ); // add custom URL
            add_filter( 'dt_metrics_menu', [ $this, 'add_menu' ], 99 );

            if ( 'metrics/streams' === $url_path ) {
                add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
            }
        }
    }

    public function has_permission(){
        $permissions = $this->permissions;
        $pass = count( $permissions ) === 0;
        foreach ( $this->permissions as $permission ){
            if ( current_user_can( $permission ) ){
                $pass = true;
            }
        }
        return $pass;
    }

    public function add_url( $template_for_url ) {
        $template_for_url['metrics/streams'] = 'template-metrics.php';
        return $template_for_url;
    }

    public function add_menu( $content ) {
        $content .= '
            <li><a href="">' .  esc_html__( 'Streams', 'disciple_tools' ) . '</a>
                <ul class="menu vertical nested" id="streams-menu" aria-expanded="true">
                    <li><a href="'. site_url( '/metrics/streams/' ) .'#cluster_map" onclick="write_streams_cluster_map()">'. esc_html__( 'Cluster Map', 'disciple_tools' ) .'</a></li>
                    <li><a href="'. site_url( '/metrics/streams/' ) .'#choropleth_map" onclick="write_streams_choropleth_map()">'. esc_html__( 'Choropleth Map', 'disciple_tools' ) .'</a></li>
                    <li><a href="'. site_url( '/metrics/streams/' ) .'#points_map" onclick="write_streams_points_map()">'. esc_html__( 'Points Map', 'disciple_tools' ) .'</a></li>
                </ul>
            </li>
            ';
        return $content;
    }

    public function scripts() {

        wp_enqueue_script( 'dt_streams_metrics', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'metrics.js', [
            'jquery',
        ], filemtime( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'metrics.js' ), true );
        wp_localize_script(
            'dt_streams_metrics', 'dtStreamsMetrics', [
                'root' => esc_url_raw( rest_url() ),
                'theme_uri' => trailingslashit( get_template_directory_uri() ),
                'plugin_uri' => plugin_dir_url( __DIR__ ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'current_user_login' => wp_get_current_user()->user_login,
                'current_user_id' => get_current_user_id(),
                'map_key' => DT_Mapbox_API::get_key(),
                "spinner_url" => get_stylesheet_directory_uri() . '/spinner.svg',
            ]
        );

    }

    public function add_api_routes() {
        $namespace = 'dt/v1';

        register_rest_route(
            $namespace, '/streams/cluster_geojson', [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'cluster_geojson' ],
                ],
            ]
        );
        register_rest_route(
            $namespace, '/streams/points_geojson', [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'points_geojson' ],
                ],
            ]
        );
        register_rest_route(
            $namespace, '/streams/grid_totals', [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'grid_totals' ],
                ],
            ]
        );
        register_rest_route(
            $namespace, '/streams/grid_country_totals', [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'grid_country_totals' ],
                ],
            ]
        );
        register_rest_route(
            $namespace, '/streams/geocode_details', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'geocode_details' ],
                ],
            ]
        );

    }

    public function cluster_geojson( WP_REST_Request $request ) {
        if ( ! $this->has_permission() ){
            return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
        }
        global $wpdb;

        /* pulling 30k from location_grid_meta table */
        $results = $wpdb->get_results("
            SELECT lg.label as address, p.post_title as name, post_id, lng, lat 
            FROM $wpdb->dt_location_grid_meta as lg 
                JOIN $wpdb->posts as p ON p.ID=lg.post_id 
            WHERE lg.post_type = 'streams' 
            LIMIT 40000
            ", ARRAY_A );
        $features = [];
        foreach ( $results as $result ) {
            $features[] = array(
                'type' => 'Feature',
                'properties' => array(
            "address" => $result['address'],
            "post_id" => $result['post_id'],
            "name" => $result['name']
            ),
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        $result['lng'],
                        $result['lat'],
                        1
                    ),
                ),
            );
        }

        $new_data = array(
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        return $new_data;
    }

    public function points_geojson( WP_REST_Request $request ) {
        if ( ! $this->has_permission() ){
            return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
        }
        global $wpdb;

        /* pulling 30k from location_grid_meta table */
        $results = $wpdb->get_results("
            SELECT lgm.label as l, p.post_title as n, lgm.post_id as pid, lgm.lng, lgm.lat, lg.admin0_grid_id as a0, lg.admin1_grid_id as a1
            FROM $wpdb->dt_location_grid_meta as lgm
                 LEFT JOIN $wpdb->posts as p ON p.ID=lgm.post_id  
                 LEFT JOIN $wpdb->dt_location_grid as lg ON lg.grid_id=lgm.grid_id   
            WHERE lgm.post_type = 'streams' 
            LIMIT 40000;
            ", ARRAY_A );
        $features = [];
        foreach ( $results as $result ) {
            $features[] = array(
                'type' => 'Feature',
                'properties' => array(
            "l" => $result['l'],
            "pid" => $result['pid'],
            "n" => $result['n'],
            "a0" => $result['a0'],
            "a1" => $result['a1']
            ),
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        $result['lng'],
                        $result['lat'],
                        1
                    ),
                ),
            );
        }

        $new_data = array(
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        return $new_data;
    }

    public function grid_totals( WP_REST_Request $request ) {
        if ( !$this->has_permission() ){
            return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
        }

        global $wpdb;
        $results = $wpdb->get_results( "
            SELECT t0.admin0_grid_id as grid_id, count(t0.admin0_grid_id) as count 
            FROM (
             SELECT lg.admin0_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t0
            GROUP BY t0.admin0_grid_id
            UNION
            SELECT t1.admin1_grid_id as grid_id, count(t1.admin1_grid_id) as count 
            FROM (
             SELECT lg.admin1_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t1
            GROUP BY t1.admin1_grid_id
            UNION
            SELECT t2.admin2_grid_id as grid_id, count(t2.admin2_grid_id) as count 
            FROM (
             SELECT lg.admin2_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t2
            GROUP BY t2.admin2_grid_id
            UNION
            SELECT t3.admin3_grid_id as grid_id, count(t3.admin3_grid_id) as count 
            FROM (
             SELECT lg.admin3_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t3
            GROUP BY t3.admin3_grid_id
            UNION
            SELECT t4.admin4_grid_id as grid_id, count(t4.admin4_grid_id) as count 
            FROM (
             SELECT lg.admin4_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t4
            GROUP BY t4.admin4_grid_id
            UNION
            SELECT t5.admin5_grid_id as grid_id, count(t5.admin5_grid_id) as count 
            FROM (
             SELECT lg.admin5_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t5
            GROUP BY t5.admin5_grid_id;
            ", ARRAY_A );


        $list = [];
        foreach ( $results as $result ) {
            $list[$result['grid_id']] = $result;
        }

        return $list;

    }

    public function grid_country_totals( WP_REST_Request $request ) {
        if ( !$this->has_permission() ){
            return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
        }
        global $wpdb;
        $results = $wpdb->get_results( "
            SELECT t0.admin0_grid_id as grid_id, count(t0.admin0_grid_id) as count 
            FROM (
             SELECT lg.admin0_grid_id FROM $wpdb->dt_location_grid as lg LEFT JOIN $wpdb->dt_location_grid_meta as lgm ON lg.grid_id=lgm.grid_id WHERE lgm.post_type = 'streams'
            ) as t0
            GROUP BY t0.admin0_grid_id
            ", ARRAY_A );

        $list = [];
        foreach ( $results as $result ) {
            $list[$result['grid_id']] = $result;
        }

        return $list;

    }

    public function geocode_details( WP_REST_Request $request ) {
        if ( !$this->has_permission() ){
            return new WP_Error( __METHOD__, "Missing Permissions", [ 'status' => 400 ] );
        }
        $params = $request->get_json_params() ?? $request->get_body_params();
        if ( isset( $params['lng'] ) && ! empty( $params['lng'] ) && isset( $params['lat'] ) && ! empty( $params['lat'] ) ) {
            $geocoder = new Location_Grid_Geocoder();
            $response = $geocoder->get_grid_id_by_lnglat( $params['lng'], $params['lat'] );

            return $response;
        } else {
            return new WP_Error( __METHOD__, "Wrong parameters", [ 'status' => 400 ] );
        }
    }

}

