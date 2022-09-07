<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

add_filter( 'dt_post_type_modules', function( $modules ){
    $modules["streams_base"] = [
        "name" => "Streams",
        "enabled" => true,
        "locked" => true,
        "prerequisites" => [ "contacts_base" ],
        "post_type" => "streams",
        "description" => "Default Streams Module"
    ];
    return $modules;
}, 20, 1 );

require_once 'module-base.php';
DT_Stream_Base::instance();

require_once 'module-reports.php';
DT_Stream_Reports::instance();
