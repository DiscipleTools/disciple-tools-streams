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
    $modules["stream_app_module"] = [
        "name" => "Streams - Apps Module",
        "enabled" => true,
        "locked" => false,
        "prerequisites" => [ "streams_base" ],
        "post_type" => "streams",
        "description" => "Add Micro App Tile to Streams"
    ];
    return $modules;
}, 20, 1 );

require_once 'module-base.php';
DT_Stream_Base::instance();

require_once 'module-app.php';
DT_Stream_Apps::instance();
DT_Stream_App_Report::instance();
