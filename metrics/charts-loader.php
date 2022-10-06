<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

// scan load
$dir = scandir( __DIR__ );
$dir = array_reverse( $dir );
foreach ( $dir as $file ){
    if ( 'metrics' === substr( $file, 0, 7 ) && 'php' === substr( $file, -3, 3 ) ){
        require_once( $file );
    }
}
