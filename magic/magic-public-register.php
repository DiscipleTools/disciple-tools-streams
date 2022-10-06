<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Streams_Self_Register extends DT_Magic_Url_Self_Register {

    public $page_title = 'Register and Retrieve Access';
    public $root = "streams_app";
    public $type = 'access';
    public $type_name = 'Access';
    public $post_type = 'streams';
    public $page_description = '';

    public $app_meta_key = 'streams_app_report_magic_key'; // target app this magic link is servicing
    public $app_p2p_connection_field = 'reporter'; // field for reporter connection
    public $app_p2p_connection_type = 'streams_to_reporter'; // found in the definition of the connection field, i.e. 'reporter'
    public $app_p2p_connection_direction = 'from'; // connection direction which manages the columns to query
    public $app_url = 'streams_app/report/'; // target app this magic link is servicing

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        parent::__construct();

        // fail if not valid url
        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        if ( !$this->check_parts_match( false ) ){
            return;
        }

        add_action( 'dt_blank_body', [ $this, 'body_register_and_retrieve' ] );
    }
}
DT_Streams_Self_Register::instance();