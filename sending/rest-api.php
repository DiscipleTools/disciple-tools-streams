<?php

/**
 * DT_Bulk_Contact_Messages_Endpoints
 *
 * @class      DT_Bulk_Contact_Messages_Endpoints
 * @version    0.1.0
 * @since      0.1.0
 * @package    Disciple.Tools
 * @author     Disciple.Tools
 */

if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class DT_Bulk_Contact_Messages_Endpoints
 */
class DT_Bulk_Contact_Messages_Endpoints
{
    /**
     * DT_Bulk_Contact_Messages_Endpoints The single instance of DT_Bulk_Contact_Messages_Endpoints.
     *
     * @var     object
     * @access    private
     * @since     0.1.0
     */
    private static $_instance = null;

    /**
     * Main DT_Bulk_Contact_Messages_Endpoints Instance
     * Ensures only one instance of DT_Bulk_Contact_Messages_Endpoints is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return DT_Bulk_Contact_Messages_Endpoints instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    /**
     * Constructor function.
     *
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    } // End __construct()

    public function add_api_routes() {
        $version = '1';
        $namespace = 'dt/v' . $version;

        $arg_schemas = [
            "post_type" => [
                "description" => "The post type",
                "type" => 'string',
                "required" => true,
                "validate_callback" => [ 'Disciple_Tools_Posts_Endpoints', "prefix_validate_args_static" ]
            ],
        ];

        register_rest_route(
            $namespace, '/(?P<post_type>\w+)/bulk_messaging', [
                [
                    "methods"  => "POST",
                    "callback" => [ $this, 'bulk_messaging' ],
                    "args" => [
                        "post_type" => $arg_schemas["post_type"],
                    ],
                    'permission_callback' => '__return_true',
                ]
            ]
        );

    }

    /**
     * Get tract from submitted address
     *
     * @param  WP_REST_Request $request
     *
     * @access public
     * @since  0.1.0
     * @return string|WP_Error|array The contact on success
     */
    public function bulk_messaging( WP_REST_Request $request ) {
        $params = $request->get_params();

        $body = '';
        if ( isset( $params['settings']['body'] ) && ! empty( $params['settings']['body'] ) ) {
            $body = sanitize_textarea_field( $params['settings']['body'] );
        }

        $params = dt_recursive_sanitize_array( $params );

        $params['settings']['body'] = $body;

        if ( isset( $params['settings']['method'] ) && 'email' === $params['settings']['method'] ) {
            return DT_Bulk_Contact_Messaging_Email::send( $params );
        } else {
            return dt_send_bulk_twilio_message( $params );
        }
    }

}
DT_Bulk_Contact_Messages_Endpoints::instance();
