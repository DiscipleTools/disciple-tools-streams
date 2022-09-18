<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

if ( strpos( dt_get_url_path(), 'streams_app' ) !== false || dt_is_rest() ){
    DT_Streams_App_Access::instance();
}

class DT_Streams_App_Access extends DT_Magic_Url_Base
{
    public $page_title = 'Reporter Manager';
    public $root = "streams_app";
    public $type = 'access';
    public $portal_key = 'streams_app_report_magic_key';
    public $portal_url = 'streams_app/report/';
    public $type_name = 'Access';
    public $post_type = 'streams';
//    private $meta_key = '';

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
        parent::__construct();

        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );

        // fail if not valid url
        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        if ( !$this->check_parts_match( false ) ){
            return;
        }

        add_action( 'dt_blank_body', [ $this, 'body' ] );
        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, '_wp_enqueue_scripts' ], 99 );

    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'access';
        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        return $allowed_css;
    }

    public function _wp_enqueue_scripts(){
        wp_enqueue_script( 'access', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'access.js', [], filemtime( plugin_dir_path( __FILE__ ) .'access.js' ), true );
    }

    public function add_endpoints() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace,
            '/'.$this->type,
            [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function endpoint( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );
        $action = sanitize_text_field( wp_unslash( $params['action'] ) );

        switch ( $action ) {
            case 'new_registration':
                return $this->create_new_reporter( $params['data'] );
            case 'send_link':
                return $this->send_reporter_link( $params['data'] );

            default:
                return new WP_Error( __METHOD__, "Missing valid action", [ 'status' => 400 ] );
        }
    }

    public function body(){
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell" id="report-content"></div>
            </div>
        </div>
        <?php
    }

    public function footer_javascript(){
        ?>
        <style>
            body {
                background-color:white;
            }
            #wrapper {
                max-width: 600px;
                margin: 1em auto;
            }
            #email {
                display:none !important;
            }
            #email-send {
                display:none !important;
            }
        </style>
        <script>
            let jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'mirror_url' => dt_get_location_grid_mirror( true ),
                'theme_uri' => trailingslashit( get_stylesheet_directory_uri() ),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'post_type' => $this->post_type,
                'trans' => [
                    'add' => __( 'Zume', 'disciple-tools-reporting-app' ),
                ],
            ]) ?>][0]
        </script>
        <?php
    }

    public function create_new_reporter( $data ) {

        if ( ! isset( $data['name'], $data['phone'], $data['email'] ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, 'Missing parameter', [ 'status' => 400 ] )
            ];
        }

        $data['email'] = sanitize_email( wp_unslash( $data['email'] ) );

        // search for email address
        $record_post_id = $this->_search_for_email( $data['email'] );
        if ( $record_post_id ) {
            $link = trailingslashit( site_url() ) . $this->portal_url . $record_post_id['magic_key'];
            $subject = __( 'Personal Reporting Link' );
            $message_plain_text = __( 'Follow this link to access your personal portal' ) . '

'           . $link;
            dt_send_email( $data['email'], $subject, $message_plain_text );

            return [
                'status' => 'EMAILED'
            ];
        }

        // prepare contact for creation
        $key = dt_create_unique_key();
        $link = trailingslashit( site_url() ) . $this->portal_url . $key;

        $fields = [
            'title' => $data['name'],
            "nickname" => $data['name'],
            'overall_status' => 'reporting_only',
            'type' => 'access',
            'assigned_to' => 0,
            "contact_phone" => [
                [
                    "value" => $data['phone']
                ]
            ],
            "contact_email" => [
                [
                    "value" => $data['email']
                ]
            ],
            "sources" => [
                "values" => [
                    [ "value" => 'self_registered_reporter' ],
                ]
            ],
            "leader_milestones" => [
                "values" => [
                    [ "value" => 'practicing' ],
                ]
            ],
            "notes" => [
                "Source" => "This contact was self-registered as a reporter."
            ],
            'streams_app_portal_magic_key' => $key
        ];

        if ( class_exists( 'DT_Ipstack_API' ) && ! empty( DT_Ipstack_API::get_key() ) ) {
            $ip_result = DT_Ipstack_API::geocode_current_visitor();
            if ( ! empty( $ip_result ) ) {
                $fields['location_grid_meta'] = [
                    'values' => [
                        [
                            'lng' => DT_Ipstack_API::parse_raw_result( $ip_result, 'lng' ),
                            'lat' => DT_Ipstack_API::parse_raw_result( $ip_result, 'lat' )
                        ]
                    ]
                ];
            }
        }

        // create contact
        $new_post = DT_Posts::create_post( 'contacts', $fields, true, false );
        if ( is_wp_error( $new_post ) ) {
            return [
                'status' => 'FAIL',
                'error' => $new_post
            ];
        }

        // email contact new magic link
        $subject = __( 'Church Reporting Link' );
        $message_plain_text = __( 'Follow this link to access your reporting portal. Please, complete your remaining community profile.' ) . '

'      . $link;
        dt_send_email( $data['email'], $subject, $message_plain_text );

        return [
            'status' => 'CREATED',
            'link' => $link
        ];
    }

    public function send_reporter_link( $data ) {
        // test if valid email
        if ( ! isset( $data['email'] ) || empty( $data['email'] ) ) {
            return new WP_Error( __METHOD__, 'email not set', [ 'status' => 400 ] );
        }

        // sanitize
        $email = sanitize_email( wp_unslash( $data['email'] ) );

        // search for email address
        $record_post_id = $this->_search_for_email( $email );

        // generate link url
        if ( ! $record_post_id ) {
            return false;
        }
        else {
            if ( ! isset( $record_post_id['magic_key'] ) ) {
                $key = dt_create_unique_key();
                add_post_meta( $record_post_id['post_id'], $this->portal_key, $key );
                $record_post_id['magic_key'] = $key;
            }

            $link = trailingslashit( site_url() ) . $this->portal_url . $record_post_id['magic_key'];
            $subject = __( 'Church Reporting Link' );
            $message_plain_text =
                __( 'Follow this link to access your reporting portal. Please, complete your remaining community profile.' ) . '

' . $link;
            dt_send_email( $email, $subject, $message_plain_text );

            return true;
        }
    }

    /**
     * @param $root
     * @param $type
     * @param $email
     * @return false|array
     */
    public function _search_for_email( $email ) {
        global $wpdb;
        $record_post_id = $wpdb->get_results($wpdb->prepare( "
            SELECT pm.post_id, pm1.meta_value as status, pm2.meta_value as magic_key
            FROM $wpdb->postmeta pm
            JOIN $wpdb->posts p ON p.ID=pm.post_id AND p.post_type = 'contacts'
            LEFT JOIN $wpdb->postmeta pm1 ON pm.post_id = pm1.post_id AND pm1.meta_key = 'overall_status'
            LEFT JOIN $wpdb->postmeta pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = %s
            WHERE  pm.meta_value = %s
        ", $this->portal_key, $email ), ARRAY_A );

        // empty or error
        if ( is_wp_error( $record_post_id ) && empty( $record_post_id ) ) {
            return false;
        }
        // found 1 match
        else if ( count( $record_post_id ) === 1 ) {
            return $record_post_id[0];
        }
        // found more than 1 match
        else {
            foreach ( $record_post_id as $row ) {
                if ( in_array( $row['status'], [ 'active', 'reporting_only' ] ) ) {
                    return $row;
                }
            }
            return false;
        }
    }
}
