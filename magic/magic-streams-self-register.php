<?php

class DT_Streams_Self_Register extends DT_Magic_Url_Self_Register {

    public $page_title = 'Reporter Manager';
    public $root = "streams_app";
    public $type = 'access';
    public $portal_key = 'streams_app_report_magic_key';
    public $portal_url = 'streams_app/report/';
    public $type_name = 'Access';
    public $post_type = 'streams';
    public $page_description = '';

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

        // fail if not valid url
        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        if ( !$this->check_parts_match( false ) ){
            return;
        }

        add_action( 'dt_blank_body', [ $this, 'body' ] );
    }

    public function header_javascript(){
        $this->register_form_header_javascript();
    }

}
DT_Streams_Self_Register::instance();