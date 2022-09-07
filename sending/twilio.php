<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

add_filter( 'dt_message_methods', function( $list ){
    $list['twilio'] = [
        'key' => 'twilio',
        'label' => 'Text Message'
    ];
    return $list;
});

// https://www.twilio.com/docs/sms/quickstart/php
require_once( trailingslashit( plugin_dir_path( __DIR__ ) ) . 'vendor/autoload.php' );
use Twilio\Rest\Client;

function dt_send_bulk_twilio_message( $params ) {

    $options = dt_bulk_contact_messaging_options();

    if ( ! isset( $options['twilio_sid'], $options['twilio_auth'], $options['twilio_number'] ) ) {
        return new WP_Error( __METHOD__, "Missing list of post ids", [ 'status' => 400 ] );
    }
    if ( empty( $options['twilio_sid'] ) ) {
        return new WP_Error( __METHOD__, "Missing twilio account sid", [ 'status' => 400 ] );
    }
    $account_sid = $options['twilio_sid'];
    if ( empty( $options['twilio_auth'] ) ) {
        return new WP_Error( __METHOD__, "Missing twilio auth", [ 'status' => 400 ] );
    }
    $auth_token = $options['twilio_auth'];
    if ( empty( $options['twilio_number'] ) ) {
        return new WP_Error( __METHOD__, "Missing twilio number", [ 'status' => 400 ] );
    }
    $twilio_number = $options['twilio_number'];

    $message = '';
    if ( isset( $params['settings']['body'] ) && ! empty( $params['settings']['body'] ) ) {
        $message = $params['settings']['body'];
    }

    // prepare app
    $app_selected = false;
    $root = '';
    $type = '';
    if ( isset( $params['settings']['root'] )
        && ! empty( $params['settings']['root'] )
        && isset( $params['settings']['type'] )
        && ! empty( $params['settings']['type'] )
    ) {
        $root = $params['settings']['root'];
        $type = $params['settings']['type'];
        $magic = new DT_Magic_URL( $root );
        $types = $magic->list_types();
        if ( ! isset( $types[$type] ) ) {
            return new WP_Error( __METHOD__, "Magic link type not found", [ 'status' => 400 ] );
        } else {
            $name = $types[$type]['name'] ?? '';
            $meta_key = $types[$type]['meta_key'];
        }
        $app_selected = true;
    }

    $send_errors = [];
    $success = [];

    $client = new Client( $account_sid, $auth_token );

    foreach ( $params['post_ids'] as $post_id ) {
        $unique_message = $message;

        $post_record = DT_Posts::get_post( $params['post_type'], $post_id, true, true );

        if ( is_wp_error( $post_record ) || empty( $post_record ) ){
            $send_errors[$post_id] = 'no record';
            continue;
        }

        if ( ! isset( $post_record['contact_phone'][0] ) ) {
            add_post_meta( $post_id, 'tags', 'No Phone', false );
            $send_errors[$post_id] = 'no phone';
            continue;
        }

        if ( $app_selected ) {
            // check if magic key exists, or needs created
            if ( ! isset( $post_record[$meta_key] ) ) {
                $key = dt_create_unique_key();
                update_post_meta( $post_id, $meta_key, $key );
                $link = DT_Magic_URL::get_link_url( $root, $type, $key );
            }
            else {
                $link = DT_Magic_URL::get_link_url( $root, $type, $post_record[$meta_key] );
            }
            $unique_message .= PHP_EOL . $link;
        }

        $phone = $post_record['contact_phone'][0]['value'];

        $result = $client->messages->create(
            $phone,
            array(
                'from' => $twilio_number,
                'body' => $unique_message
            )
        );

        dt_write_log( $result );

        $success[] = $post_id;
    }

    return [
        'total_unsent' => ( ! empty( $send_errors ) ) ? count( $send_errors ) : 0,
        'total_sent' => ( ! empty( $success ) ) ? count( $success ) : 0,
        'errors' => $send_errors,
        'success' => $success
    ];
}

