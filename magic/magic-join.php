<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Streams_Invite_To_Join_Stream extends DT_Magic_Url_Self_Register {

    public $magic = false;
    public $parts = false;
    public $page_title = 'Invite to Join this Stream';
    public $page_description = 'Invite to Join this Stream';
    public $root = 'streams_app';
    public $type = 'join';
    public $post_type = 'streams';
    private $meta_key = '';
    public $show_bulk_send = false;
    public $show_app_tile = true;

    public $app_meta_key = 'streams_app_report_magic_key'; // target app this magic link is servicing
    public $app_p2p_connection_field = 'reporter'; // field for reporter connection
    public $app_p2p_connection_type = 'streams_to_reporter'; // found in the definition of the connection field, i.e. 'reporter'
    public $app_p2p_connection_direction = 'from'; // connection direction which manages the columns to query
    public $app_url = 'streams_app/report/'; // target app this magic link is servicing

    private static $_instance = null;
    public $meta = [];

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
        parent::__construct();

        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        if ( !$this->check_parts_match() ){
            return;
        }

        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'body_join' ] ); // body for no post key
    }
}
DT_Streams_Invite_To_Join_Stream::instance();