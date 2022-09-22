<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.


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
                return $this->register_to_report( $params['data'] );
            case 'retrieve_link':
                return $this->retrieve_stream_link( $params['data'] );

            default:
                return new WP_Error( __METHOD__, "Missing valid action", [ 'status' => 400 ] );
        }
    }

    public function body(){
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell" id="content"></div>
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

    public function register_to_report( $data ) {

        if ( ! isset( $data['name'], $data['phone'], $data['email'], $data['stream_name'] ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, 'Missing parameter', [ 'status' => 400 ] )
            ];
        }

        $display_name = sanitize_text_field( wp_unslash( $data['name'] ) );
        $phone = sanitize_text_field( wp_unslash( $data['phone'] ) );

        $stream_name = sanitize_text_field( wp_unslash( $data['stream_name'] ) );
        $user_email = sanitize_email( wp_unslash( $data['email'] ) );

        $identity = $this->_build_identity( $user_email );
        dt_write_log($identity);

        // has user_id and has streams
        if ( isset( $identity['stream_ids'] ) && ! empty( $identity['stream_ids'] ) ) {
            $this->_send_to_user( $identity );
            dt_write_log('has user_id and has streams');
            return [
                'status' => 'EMAILED'
            ];
        }

        // has a user id, but does not have a contact_id. This should not happen.
        if( ! empty( $identity['user_id'] ) && empty( $identity['contact_ids'] ) ) {
            dt_write_log('has a user id, but does not have a contact_id. This should not happen.');
            return [
                'status' => 'FAIL',
                'message' => 'User ID exists but contact has not been created'
            ];
        }

        // has user_id but has no streams
        if ( ! empty( $identity['user_id'] ) && empty( $identity['stream_ids'] ) ) {
            $new_stream = $this->_create_stream( $identity, $stream_name );
            dt_write_log('has user_id but has no streams');
            dt_write_log($new_stream);

            if ( is_wp_error( $new_stream ) ) {
                return [
                    'status' => 'FAIL',
                    'error' => $new_stream
                ];
            }
            // rebuild identity
            $identity = $this->_build_identity( $user_email );
            $send_result = $this->_send_to_user( $identity );
            dt_write_log($send_result);
            return [
                'status' => 'EMAILED'
            ];
        }

        return;

        if ( empty( $identity['user_id'] ) ) {
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
                        "value" => $user_email
                    ]
                ],
                "sources" => [
                    "values" => [
                        [ "value" => 'self_registered_stream' ],
                    ]
                ],
                "notes" => [
                    "Source" => "This contact was self-registered as a stream."
                ]
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

            dt_write_log('$new_post');
            dt_write_log($new_post);


            // create user and contact_id

            $exploded_email = explode( '@', $user_email );

            $user_name_first = strtolower( $exploded_email[0] );
            $user_name_last = dt_create_field_key( dt_create_unique_key() );
            $user_name = $user_name_first . $user_name_last;
            if ( username_exists( $user_name ) ) {
                $user_name = $user_name_first . dt_create_field_key( dt_create_unique_key() );
            }

            $user_roles = [ 'reporting_only' ];

            $current_user = wp_get_current_user();
            $current_user->add_cap('create_users', true );
            $current_user->add_cap('create_contacts', true );
            $current_user->add_cap('update_any_contacts', true );

            dt_write_log('$current_user');
            dt_write_log($current_user);

            $contact_id = Disciple_Tools_Users::create_user( $user_name, $user_email, $display_name, $user_roles, $new_post );
            dt_write_log('$contact_id');
            dt_write_log($contact_id);

            // create stream
            // assign contact as reporter

        }

        return;

        // prepare contact for creation


        // email contact new magic link
        $subject = __( 'Church Reporting Link' );
        $message_plain_text = __( 'Follow this link to access your reporting portal. Please, complete your remaining community profile.' ) . '

'      . $link;
        dt_send_email( $email, $subject, $message_plain_text );

        return [
            'status' => 'CREATED',
            'link' => $link
        ];
    }

    public function _create_stream( $identity, $stream_name ) {
        if ( isset( $identity['contact_ids'][0] ) ) {
            $contact_id = $identity['contact_ids'][0];
        } else {
            return new WP_Error( __METHOD__, 'No contact id set', [ 'status' => 400 ] );
        }
        $magic = new DT_Magic_URL( 'streams_app' );
        $fields = [
            'title' => $stream_name,
            'reporter' => [
                'values' => [
                    [ 'value' => $contact_id ]
                ],
            ],
            'streams_app_report_magic_key' => $magic->create_unique_key(),
            "notes" => [
                "Source" => "This stream was self-registered."
            ]
        ];

        return DT_Posts::create_post( 'streams', $fields, true, false );
    }

    public function retrieve_stream_link( $data ) {
        // test if valid email
        if ( ! isset( $data['email'] ) || empty( $data['email'] ) ) {
            return new WP_Error( __METHOD__, 'email not set', [ 'status' => 400 ] );
        }

        $email = sanitize_email( wp_unslash( $data['email'] ) );

        $identity = $this->_build_identity( $email );
        if ( isset( $identity['stream_ids'] ) && ! empty( $identity['stream_ids'] ) ) {
            $this->_send_to_user( $identity );
            return true;
        } else {
            dt_write_log('No identity found');
            return false;
        }
    }

    public function _send_to_user( $identity ) {
        dt_write_log(__METHOD__);
        dt_write_log( $identity );

        if ( isset( $identity['stream_ids'], $identity['email'] ) ) {
            $message_plain_text = __('Follow this link to access your reporting portal. Please, complete your remaining community profile.') . '

';
            foreach ($identity['stream_ids'] as $stream) {
                $link = trailingslashit(site_url()) . $this->portal_url . $stream['magic_key'];
                $message_plain_text .=
                    'Reporting Access for ' . $stream['name'] . ':' . '

' . $link . '

';
            }

            $subject = __('Reports Access');
            return dt_send_email($identity['email'], $subject, $message_plain_text);
        }
    }

    public function _build_identity( $email ) {
        dt_write_log(__METHOD__);

        $user_id = $this->_query_for_user_id( $email ); // int|false

        $contact_ids = $this->_query_for_contact_ids( $user_id, $email ); // id array | empty array

        $stream_ids = $this->_query_for_streams( $contact_ids );

        return [
            'user_id' => $user_id,
            'email' => $email,
            'contact_ids' => $contact_ids,
            'stream_ids' => $stream_ids,
        ];
    }

    public function _query_for_user_id( $email ) {
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID as user_id
                    FROM $wpdb->users
                    WHERE user_email = %s;
                    ", $email ) );
        if ( is_wp_error( $id ) ) {
            return false;
        }
        return $id;
    }

    public function _query_for_contact_ids( $user_id, $email ) {
        global $wpdb;
        $contact_ids = [];
        if ( $user_id ) {
            $meta_key = $wpdb->prefix . 'corresponds_to_contact';
            $id = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value as contact_id
                        FROM $wpdb->usermeta
                        WHERE user_id = %d AND meta_key = %s;
                    ", $user_id, $meta_key ) );
            if ( is_wp_error( $id ) ) {
                return [];
            }
            $contact_ids[] = $id;
            return $contact_ids;
        } else {
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT pm.post_id 
                        FROM $wpdb->postmeta pm
                        JOIN $wpdb->posts p ON p.ID=pm.post_id AND p.post_type = 'contacts'
                        WHERE pm.meta_key LIKE 'contact_email%' AND pm.meta_value = %s;
                    ", $email ));
            if ( is_wp_error( $ids ) ) {
                return [];
            }
            foreach( $ids as $id ) {
                $contact_ids[] = $id;
            }
            return $contact_ids;
        }
    }

    public function _query_for_streams( $contact_ids ) {
        global $wpdb;
        if ( empty( $contact_ids ) || ! is_array( $contact_ids ) ) {
            return [];
        }
        $streams = [];
        foreach( $contact_ids as $contact_id ) {
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT %d as contact_id, p2p_from as stream_id, meta_value as magic_key, post_title as name
                        FROM $wpdb->p2p p2
                        LEFT JOIN $wpdb->postmeta pm ON pm.post_id=p2.p2p_from AND pm.meta_key = 'streams_app_report_magic_key'
                        LEFT JOIN $wpdb->posts p ON p.ID=pm.post_id
                        WHERE p2.p2p_type = 'streams_to_reporter' AND p2.p2p_to = %d;
                    ", $contact_id, $contact_id ), ARRAY_A );
            if ( is_wp_error( $results ) ) {
                continue;
            }

            $streams = array_merge( $streams, $results );
        }
        return $streams;

    }

    public function _query_for_identity( $email ) {
        global $wpdb;

        // parse via user search
        $user_object = get_user_by('email', $email );
        if ( $user_object ) {
            $contact_id = Disciple_Tools_Users::get_contact_for_user( $user_object->ID );
            if ( is_wp_error( $contact_id ) ) {
                return $contact_id;
            }
            else if ( ! empty( $contact_id ) ) {
                // happy path
                return [
                    'user_id' => $user_object->ID,
                    'contact_id' => $contact_id,
                    'email' => $email
                ];
            }
            else {
                // is a user but has no contact record ?????
            }
        }

        // parse via contact search
        $connected_contacts = $wpdb->get_results("
            SELECT DISTINCT p.ID as post_id, pm.meta_value as email, pmu.meta_value as user_id
            FROM $wpdb->posts p
            JOIN $wpdb->p2p p2 ON p2.p2p_to=p.ID AND p2.p2p_type = 'streams_to_reporter'
            JOIN $wpdb->postmeta pm ON pm.post_id=p.ID AND pm.meta_key LIKE 'contact_email%' AND pm.meta_key NOT LIKE '%_details'
            LEFT JOIN $wpdb->postmeta pmu ON p.ID=pmu.post_id AND pmu.meta_key = 'corresponds_to_user'
            WHERE p.post_type = 'contacts'
        ");

    }

    /**
     * @param $root
     * @param $type
     * @param $email
     * @return false|array
     */
    public function _search_for_email( $email ) {
        global $wpdb;

        // is this a user
        // does this user have a contact
        // is this contact connected to a number of streams
        // what are the magic links connected to the streams

        $connected_contacts = $wpdb->get_results("
            SELECT DISTINCT p.ID as post_id, pm.meta_value as email, pmu.meta_value as user_id
            FROM $wpdb->posts p
            JOIN $wpdb->p2p p2 ON p2.p2p_to=p.ID AND p2.p2p_type = 'streams_to_reporter'
            JOIN $wpdb->postmeta pm ON pm.post_id=p.ID AND pm.meta_key LIKE 'contact_email%' AND pm.meta_key NOT LIKE '%_details'
            LEFT JOIN $wpdb->postmeta pmu ON p.ID=pmu.post_id AND pmu.meta_key = 'corresponds_to_user'
            WHERE p.post_type = 'contacts'
        ");

        // search users
        $user_object = get_user_by('email', $email );
        if ( $user_object ) {
            if ( is_multisite() ) {
                // check if user is part of multi site, if not added them with reporting_only permissions
            }

            // check if user has contact record, if not create contact record
            $contact_id = Disciple_Tools_Users::get_contact_for_user( $user_object->ID );
            dt_write_log('$contact_id');
            dt_write_log($contact_id);
            // check if user has role reporting_only or greater


        }
        // else test if a contact record exists with the email or phone provided
        else {
            $record_post_id = $wpdb->get_results($wpdb->prepare( "
                SELECT pm.post_id, pm1.meta_value as status
                FROM $wpdb->postmeta pm
                JOIN $wpdb->posts p ON p.ID=pm.post_id AND p.post_type = 'contacts'
                LEFT JOIN $wpdb->postmeta pm1 ON pm.post_id = pm1.post_id AND pm1.meta_key = 'overall_status'
                LEFT JOIN $wpdb->postmeta pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key LIKE %s
                WHERE  pm.meta_value = %s
            ", $wpdb->esc_like('contact_email').'%', $email ), ARRAY_A );
            if ( is_wp_error( $record_post_id ) && empty( $record_post_id ) ) {
                return false;
            }
        }

//        $search_query = [
//            'text' => $email
//        ];
//        $search_query = json_encode($search_query);
//        $contacts = DT_Posts::advanced_search( $search_query, 'contacts', 0 );
//        dt_write_log($contacts);

        // search contacts
        return false;
        // empty or error

        // found 1 match
         if ( count( $record_post_id ) === 1 ) {
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
DT_Streams_App_Access::instance();