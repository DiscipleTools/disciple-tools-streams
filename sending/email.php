<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

class DT_Bulk_Contact_Messaging_Email {

    public static function send( $params ) {

        if ( ! isset( $params['post_ids'] ) || empty( $params['post_ids'] ) ) {
            return new WP_Error( __METHOD__, "Missing list of post ids", [ 'status' => 400 ] );
        }

        if ( ! isset( $params['settings'] ) || empty( $params['settings'] ) ) {
            return new WP_Error( __METHOD__, "Missing settings param", [ 'status' => 400 ] );
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

        // prepare body
        $message = '';
        if ( isset( $params['settings']['body'] ) && ! empty( $params['settings']['body'] ) ) {
            $message = "" . $params['settings']['body'] . "" . PHP_EOL;
        }

        $options = dt_bulk_contact_messaging_options();

        $base_subject = get_option( 'dt_email_base_subject' );
        if ( 'default' === $params['settings']['send_from'] ) {
            $from_name = $base_subject;
            if ( ! empty( $options['from_name'] ) ) {
                $from_name = $options['from_name'];
            }
        }
        else {
            $from_name = $params['settings']['send_from'] . ' via ' . $base_subject;
            if ( ! empty( $options['from_name'] ) ) {
                $from_name = $params['settings']['send_from'] . ' via ' . $options['from_name'];
            }
        }

        // prepare subject
        if ( isset( $params['settings']['subject'] ) && ! empty( $params['settings']['subject'] ) ) {
            $subject = $params['settings']['subject'];
        }
        else if ( ! empty( $base_subject ) ) {
            $subject = 'Message from ' . $base_subject;
        }
        else {
            $subject = 'Message from ' . $from_name;
        }

        $from_email = get_bloginfo( 'admin_email' );
        if ( ! empty( $options['from_email'] ) ) {
            $from_email = $options['from_email'];
        }

        $send_errors = [];
        $success = [];

        foreach ( $params['post_ids'] as $post_id ) {
            $unique_message = $message;

            $post_record = DT_Posts::get_post( $params['post_type'], $post_id, true, true );

            if ( is_wp_error( $post_record ) || empty( $post_record ) ){
                $send_errors[$post_id] = 'no permission';
                continue;
            }

            // build email
            if ( ! isset( $post_record['contact_email'][0] ) || ! is_email( $post_record['contact_email'][0]['value'] ) ) {
                add_post_meta( $post_id, 'tags', 'No Email', false );
                $send_errors[$post_id] = 'no email';
                continue;
            }
            $to_address = $post_record['contact_email'][0]['value'];
            $to_name = $post_record['title']; // User <user@example.com>

            // build app
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

                $unique_message .= PHP_EOL . $name . ': <' . $link . '>';
            }

            $sent = self::bulk_mail( $to_address, $to_name, $from_email, $from_name, $subject, $unique_message );
            if ( is_wp_error( $sent ) || ! $sent ) {
                $send_errors[$post_id] = $sent;
            }
            else {
                $success[$post_id] = $sent;
                dt_activity_insert( [
                    'action'            => 'sent_app_link',
                    'object_type'       => $params['post_type'],
                    'object_subtype'    => 'email',
                    'object_id'         => $post_id,
                    'object_name'       => $post_record['title'],
                    'object_note'       => 'Email sent to ' . $to_address
                ] );
            }
        }

        return [
            'total_unsent' => ( ! empty( $success ) ) ? count( $send_errors ) : 0,
            'total_sent' => ( ! empty( $success ) ) ? count( $success ) : 0,
            'errors' => $send_errors,
            'success' => $success
        ];
    }

    public static function bulk_mail( $to_email, $to_name, $from_email, $from_name, $subject, $message ) {
        global $phpmailer;

        // (Re)create it, if it's gone missing.
        if ( ! ( $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            $phpmailer = new PHPMailer\PHPMailer\PHPMailer( true ); // phpcs:ignore

            $phpmailer::$validator = static function ( $email ) {
                return (bool) is_email( $email );
            };
        }

        // Empty out the values that may be set.
        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();
        $phpmailer->clearReplyTos();

        if ( ! empty( $from_email ) ) {
            try {
                $phpmailer->setFrom( $from_email, $from_name, false );
            } catch ( PHPMailer\PHPMailer\Exception $e ) {
                dt_write_log( $e );
                return false;
            }
        }

        if ( ! empty( $from_email ) ) {
            try {
                $phpmailer->addReplyTo( $from_email, $from_name );
            } catch ( PHPMailer\PHPMailer\Exception $e ) {
                dt_write_log( $e );
                return false;
            }
        }

        // Set to use PHP's mail().
        $phpmailer->isMail();

        $phpmailer->addAddress( $to_email, $to_name );

        // Set mail's subject and body.
        // phpcs:disable
        $phpmailer->Subject = $subject;
        $phpmailer->Body    = $message;
        $phpmailer->ContentType = 'text/plain';
        $phpmailer->isHTML( false );
        $phpmailer->CharSet = get_bloginfo( 'charset' );
        // phpcs:enable

        try {
            return $phpmailer->send();
        } catch ( PHPMailer\PHPMailer\Exception $e ) {
            dt_write_log( $e );
            return false;
        }
    }
}
