<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

abstract class DT_Magic_Url_Self_Register extends DT_Magic_Url_Base
{

    public function __construct() {
        parent::__construct();
        add_action( 'rest_api_init', [ $this, '_add_endpoints_self_register' ] );
    }

    public function _add_endpoints_self_register() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace,
            '/'.$this->type.'/self_register',
            [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, '_endpoint_self_register' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function _endpoint_self_register( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['action'], $params['parts']['post_type'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );
        $action = sanitize_text_field( wp_unslash( $params['action'] ) );

        switch ( $action ) {
            case 'retrieve':
                return $this->retrieve( $params['data'] );
            case 'register':
                return $this->register( $params['parts'], $params['data'] );
            case 'join':
                return $this->join( $params['parts'], $params['data'] );
            case 'create_child':
                return $this->create_child( $params['parts'], $params['data'] );

            default:
                return new WP_Error( __METHOD__, "Missing valid action", [ 'status' => 400 ] );
        }
    }

    /**
     * @param $data
     * @param $post_type
     * @return bool|WP_Error
     */
    public function retrieve( $data ) {
        // test if valid email
        if ( ! isset( $data['email'] ) || empty( $data['email'] ) ) {
            return new WP_Error( __METHOD__, 'email not set', [ 'status' => 400 ] );
        }

        $email = sanitize_email( wp_unslash( $data['email'] ) );

        $identity = $this->build_identity( $email );

        if ( isset( $identity['post_type_ids'] ) && ! empty( $identity['post_type_ids'] ) ) {
            $this->_send_to_user( $identity );
            return true;
        } else {
            dt_write_log( 'No identity found' );
            return false;
        }
    }

    public function register( $parts, $data ) {

        if ( ! isset( $data['email'] ) ) {
            return new WP_Error( __METHOD__, 'Missing email parameter', [ 'status' => 400 ] );
        }

        $name = sanitize_text_field( wp_unslash( $data['name'] ) );
        $email = sanitize_email( wp_unslash( $data['email'] ) );
        $post_type_name = sanitize_text_field( wp_unslash( $data['post_type_name'] ) );

        $identity = $this->build_identity( $email );

        $contact_id = $this->_has_contact_id( $identity );

        if ( ! $contact_id ) {
            $user = $this->_create_user( $name, $email );
            if ( is_wp_error( $user ) ) {
                return $user;
            }
            $contact_id = $user['corresponds_to_contact'];
            if ( ! $contact_id ) {
                return new WP_Error( __METHOD__, 'Failed to create a contact id', [ 'status' => 400 ] );
            }
        }

        $app_p2p_connection_field = $this->app_p2p_connection_field;
        $app_meta_key = $this->app_meta_key;
        $fields = [
            'title' => $post_type_name,
            $app_p2p_connection_field => [
                'values' => [
                    [ 'value' => $contact_id ]
                ]
            ],
            $app_meta_key => dt_create_unique_key(),
        ];
        $new_post = DT_Posts::create_post( $parts['post_type'], $fields, true, false );
        dt_write_log( $new_post );

        $identity = $this->build_identity( $email );
        $send_result = $this->_send_to_user( $identity );
        return [
            'status' => 'EMAILED',
            'message' => $send_result
        ];

        if ( is_wp_error( $new_post ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, $new_post )
            ];
        }
        return [
            'status' => 'EMAILED',
            'url' => trailingslashit( site_url() ) . $this->app_url . $new_post[$app_meta_key],
            'message' => $new_post
        ];
    }

    public function join( $parts, $data ) {
        if ( ! isset( $data['email'] ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, 'Missing parameter', [ 'status' => 400 ] )
            ];
        }

        $name = sanitize_text_field( wp_unslash( $data['name'] ) );
        $email = sanitize_email( wp_unslash( $data['email'] ) );
        $identity = $this->build_identity( $email );
        $target_post_id = $parts['post_id'];

        $contact_id = $this->_has_contact_id( $identity );
        if ( ! $contact_id ) {

            $contact_id = $this->_create_user( $name, $email );
            if ( ! $contact_id ) {
                return [
                    'status' => 'FAIL',
                    'error' => new WP_Error( __METHOD__, 'Failed to create a contact_id', [ 'status' => 400 ] )
                ];
            }
        }

        $app_p2p_connection_field = $this->app_p2p_connection_field;
        $fields = [
            $app_p2p_connection_field => [
                'values' => [
                    [ 'value' => $contact_id ]
                ]
            ]
        ];
        $updated_post = DT_Posts::update_post( $parts['post_type'], $parts['post_id'], $fields, true, false );

        $app_key = get_post_meta( $target_post_id, $this->app_meta_key, true );

        if ( is_wp_error( $updated_post ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, $updated_post )
            ];
        }
        return [
            'status' => 'EMAILED',
            'url' => trailingslashit( site_url() ) . $this->app_url . $app_key,
            'message' => $updated_post
        ];
    }

    public function create_child( $parts, $data ) {

        if ( ! isset( $data['email'] ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, 'Missing parameter', [ 'status' => 400 ] )
            ];
        }

        $name = sanitize_text_field( wp_unslash( $data['name'] ) );
        $email = sanitize_email( wp_unslash( $data['email'] ) );
        $post_type_name = sanitize_text_field( wp_unslash( $data['post_type_name'] ) );

        $identity = $this->build_identity( $email );

        $target_post_id = $parts['post_id'];

        $contact_id = $this->_has_contact_id( $identity );
        if ( ! $contact_id ) {
            $user = $this->_create_user( $name, $email );
            $contact_id = $user['corresponds_to_contact'];
            if ( ! $contact_id ) {
                return [
                    'status' => 'FAIL',
                    'error' => new WP_Error( __METHOD__, 'Failed to create a contact_id', [ 'status' => 400 ] )
                ];
            }
        }

        $app_p2p_connection_field = $this->app_p2p_connection_field;
        $app_meta_key = $this->app_meta_key;
        $fields = [
            'title' => $post_type_name,
            $app_p2p_connection_field => [
                'values' => [
                    [ 'value' => $contact_id ]
                ]
            ],
            $app_meta_key => dt_create_unique_key(),
            'parent_streams' => [
                'values' => [
                    [ 'value' => $target_post_id ]
                ]
            ],
        ];
        $new_post = DT_Posts::create_post( $parts['post_type'], $fields, true, false );

        if ( is_wp_error( $new_post ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error( __METHOD__, $new_post )
            ];
        }
        return [
            'status' => 'EMAILED',
            'url' => trailingslashit( site_url() ) . $this->app_url . $new_post[$app_meta_key],
            'message' => $new_post
        ];
    }

    public function build_identity( $email ) {

        $user_id = $this->_query_for_user_id( $email ); // int|false

        $contact_ids = $this->_query_for_contact_ids( $user_id, $email ); // id array | empty array

        $post_type_ids = $this->_query_for_posts( $contact_ids );

        return [
            'user_id' => $user_id,
            'email' => $email,
            'contact_ids' => $contact_ids,
            'post_type_ids' => $post_type_ids,
        ];
    }

    public function _create_user( $name, $email ) {

        $has_capabilities_to_this_site = true;
        $user_id = $this->_query_for_user_id( $email );
        dt_write_log( $user_id );
        if ( is_multisite() ) {
            $has_capabilities_to_this_site = $this->_test_if_user_is_site_member( $user_id );
        }
        if ( $user_id && $has_capabilities_to_this_site ) {
            // @todo create a contact for the user

            // check the meta_key for contact_id
            // create a contact_id for the user
            $identity = $this->build_identity( $email );

            if ( isset( $identity['contact_ids'][0] ) ) {
                return $identity['contact_ids'][0];
            } else {
                $contact_id = Disciple_Tools_Users::create_contact_for_user( $user_id );
                if ( is_wp_error( $contact_id ) ) {
                    return $contact_id;
                }
            }
        }

        $user_name = str_replace( ' ', '_', strtolower( $name ) );
        $user_roles = [ 'reporter' ];

        $current_user = wp_get_current_user();
        $current_user->add_cap( 'create_users', true );
        $current_user->add_cap( 'access_contacts', true );
        $current_user->add_cap( 'create_contacts', true );
        $current_user->add_cap( 'update_any_contacts', true );

        $user = Disciple_Tools_Users::create_user( $user_name, $email, $name, $user_roles, null, null, true );
        if ( is_wp_error( $user ) ) {
            return false;
        }
        return $user;
    }

    public function _test_if_user_is_site_member( $user_id ) : bool {
        global $wpdb;
        $has_capabilities_to_this_site = get_user_meta( $user_id, $wpdb->prefix . 'capabilities' );
        if ( $has_capabilities_to_this_site  ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $email
     * @return false|int
     */
    public function _query_for_user_id( $email ) {
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID as user_id
                FROM $wpdb->users
                WHERE user_email = %s;
                ", $email ) );
        if ( is_wp_error( $id ) || empty( $id ) ) {
            return false;
        }

        return (int) $id;
    }

    /**
     * @param $user_id
     * @param $email
     * @return array
     */
    public function _query_for_contact_ids( $user_id, $email ) {
        global $wpdb;
        $contact_ids = [];

        // if user id exists
        if ( $user_id ) {
            $meta_key = $wpdb->prefix . 'corresponds_to_contact';
            $id = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value as contact_id
                    FROM $wpdb->usermeta
                    WHERE user_id = %d AND meta_key = %s;
                ", $user_id, $meta_key ) );
            if ( is_wp_error( $id ) || empty( $id ) ) {
                return [];
            }
            $contact_ids[] = $id;
            return $contact_ids;
        }

        // if user id does not exist
        else {
            // @todo prioritize active contact to the top 0 position
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT pm.post_id 
                    FROM $wpdb->postmeta pm
                    JOIN $wpdb->posts p ON p.ID=pm.post_id AND p.post_type = 'contacts'
                    LEFT JOIN $wpdb->postmeta pms ON pms.post_id = pm.post_id AND pms.meta_key = 'overall_status'
                    WHERE pm.meta_key LIKE %s AND pm.meta_value = %s
                    ORDER BY pms.meta_value ASC;
                ", $wpdb->esc_like( 'contact_email' ) . '%', $email ) );
            if ( is_wp_error( $ids ) || empty( $ids ) ) {
                return [];
            }
            foreach ( $ids as $id ) {
                $contact_ids[] = $id;
            }
            return $contact_ids;
        }
    }

    /**
     * @param $contact_ids
     * @return array
     */
    public function _query_for_posts( $contact_ids ) {
        global $wpdb;
        $post_objects = [];
        if ( empty( $contact_ids ) || ! is_array( $contact_ids ) ) {
            return $post_objects;
        }

        /**
         * Required fields to be setup in extending class
         * should be added as public variables
         * -- public $app_meta_key = 'streams_app_report_magic_key';
         * -- public $app_p2p_connection_type = 'streams_to_reporter';
         * -- public $app_p2p_connection_direction = 'from';
         */
        $app_meta_key = $this->app_meta_key;
        $app_p2p_connection_type = $this->app_p2p_connection_type;
        $app_p2p_connection_direction = $this->app_p2p_connection_direction ?? 'from';
        if ( ! isset( $app_meta_key, $app_p2p_connection_type ) || empty( $app_meta_key ) || empty( $app_p2p_connection_type ) ) {
            return $post_objects;
        }

        foreach ( $contact_ids as $contact_id ) {
            if ( 'to' === $app_p2p_connection_direction ) {
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT %d as contact_id, p2.p2p_to as post_id, pm.meta_value as magic_key, p.post_title as name
                    FROM $wpdb->p2p p2
                    LEFT JOIN $wpdb->postmeta pm ON pm.post_id=p2.p2p_to AND pm.meta_key = %s
                    LEFT JOIN $wpdb->posts p ON p.ID=pm.post_id
                    WHERE p2.p2p_type = %s AND p2.p2p_from = %d;
                ", $contact_id, $app_meta_key, $app_p2p_connection_type, $contact_id ), ARRAY_A );
            }
            else { // direction from
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT %d as contact_id, p2.p2p_from as post_id, pm.meta_value as magic_key, p.post_title as name
                    FROM $wpdb->p2p p2
                    LEFT JOIN $wpdb->postmeta pm ON pm.post_id=p2.p2p_from AND pm.meta_key = %s
                    LEFT JOIN $wpdb->posts p ON p.ID=pm.post_id
                    WHERE p2.p2p_type = %s AND p2.p2p_to = %d;
                ", $contact_id, $app_meta_key, $app_p2p_connection_type, $contact_id ), ARRAY_A );
            }

            if ( is_wp_error( $results ) || empty( $results ) ) {
                continue;
            }

            $post_objects = array_merge( $post_objects, $results );
        }
        return $post_objects;
    }

    private function _is_a_user( $identity ) : bool {
        if ( isset( $identity['user_id'] ) && ! empty( $identity['user_id'] ) ) {
            return true;
        }
        return false;
    }

    public function _send_to_user( $identity ) {
        if ( isset( $identity['post_type_ids'], $identity['email'] ) ) {
            $message_plain_text = __( 'Follow this link to access your reporting portal.' ) . '

';
            foreach ($identity['post_type_ids'] as $post_object) {
                $link = trailingslashit( site_url() ) . $this->app_url . $post_object['magic_key'];
                $message_plain_text .=
                    'Reporting Access for ' . $post_object['name'] . ':

' . $link . '

';
            }

            $subject = __( 'Reports Access' );
            return dt_send_email( $identity['email'], $subject, $message_plain_text );
        }
    }

    public function _create_post( $identity, $post_type_name ) {
        if ( isset( $identity['contact_ids'][0] ) ) {
            $contact_id = $identity['contact_ids'][0];
        } else {
            return new WP_Error( __METHOD__, 'No contact id set', [ 'status' => 400 ] );
        }

        $magic = new DT_Magic_URL( $this->post_type );
        $fields = [
            'title' => $post_type_name,
            $this->app_p2p_connection_field => [
                'values' => [
                    [ 'value' => $contact_id ]
                ],
            ],
            $this->app_meta_key => $magic->create_unique_key(),
            "notes" => [
                "Source" => "This record was self-registered."
            ]
        ];

        return DT_Posts::create_post( 'streams', $fields, true, false );
    }

    public function _has_contact_id( $identity ) {
        if ( isset( $identity['contact_ids'] ) && ! empty( $identity['contact_ids'] ) ) {
            return $identity['contact_ids'][0] ?? false;
        }
        return false;
    }

    public function javascript_object() {
        ?>
        <script>
            let jsMagicSR = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'mirror_url' => dt_get_location_grid_mirror( true ),
                'theme_uri' => trailingslashit( get_stylesheet_directory_uri() ),
                'site_url' => site_url(),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'post_type' => $this->post_type,
                'app_meta_key' => $this->app_meta_key ?? '',
                'app_p2p_connection_type' => $this->app_p2p_connection_type ?? '',
                'app_p2p_connection_direction' => $this->app_p2p_connection_direction ?? '',
                'app_url' => $this->app_url ?? '',
                'title' => get_the_title( $this->parts['post_id'] ),
                'trans' => [
                    'add' => __( 'Zume', 'disciple-tools' ),
                ],
            ]) ?>][0]
            jQuery(document).ready(function() {
                window.api_sr = (action, data) => {
                    return jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify({action: action, parts: jsMagicSR.parts, data: data}),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsMagicSR.root + jsMagicSR.parts.root + '/v1/' + jsMagicSR.parts.type + '/self_register',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsMagicSR.nonce)
                        }
                    })
                        .fail(function (e) {
                            jQuery('#error').html(e)
                        })
                }
            })
        </script>
        <?php
    }

    public function body_register_and_retrieve() {
        $this->javascript_object();
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <style>
            body { background-color:white; } #wrapper { max-width: 600px; margin: 1em auto; } #email { display:none !important; } #email-send { display:none !important; }
        </style>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell" id="content">
                    <input type="hidden" id="stream-grid-id" />
                    <div id="panel1" class="first not-new not-send">
                        <div class="grid-x">
                            <div class="cell">
                                <button type="button" class="button large expanded show-new">Create a New Stream</button>
                            </div>
                            <div class="cell">
                                <button type="button" class="button large expanded show-send">Retrieve My Private Link</button>
                            </div>
                        </div>
                    </div>

                    <div id="new-panel" class="new not-first not-send" style="display:none;">
                        <div class="grid-x">
                            <div class="cell panel-note"></div>
                            <div class="cell">
                                <label for="post_type_name">Name, Nickname, or City of your movement</label>
                                <input type="text" id="post_type_name" class="required" placeholder="Name or nickname of your movement" />
                                <span id="stream-name-error" class="form-error">You're name is required.</span>
                            </div>
                            <div class="cell">
                                <label for="name">Name</label>
                                <input type="text" id="name" class="required" placeholder="Your name" />
                                <span id="name-error" class="form-error">You're name is required.</span>
                            </div>

                            <div class="cell">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email" />
                                <input type="email" id="e2" name="email" class="required" placeholder="Email" />
                                <span id="email-error" class="form-error">You're email is required.</span>
                            </div>
                            <div class="cell center">
                                <button class="button large" id="submit-new">Create a New Stream</button> <span class="loading-spinner"></span><br>
                                <a class="show-first">back</a>
                            </div>
                        </div>
                    </div>

                    <div id="send-panel" class="send not-new not-first" style="display:none;">
                        <div class="grid-x">
                            <div class="cell">
                                <label for="email">Email</label>
                                <input type="email" id="email-send" name="email" placeholder="Email" />
                                <input type="email" id="e2-send" name="email" class="required" placeholder="Email" />
                                <span id="email-error-send" class="form-error">You're email is required.</span>
                            </div>
                            <div class="cell center">
                                <button class="button large" id="submit-send-link">Email me access link</button> <span class="loading-spinner"></span><br>
                                <a class="show-first">back</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function(){

                // set listeners
                let send = jQuery('.send')
                let not_send = jQuery('.not-send')
                let new_input = jQuery('.new')
                let not_new = jQuery('.not-new')
                let first = jQuery('.first')
                let not_first = jQuery('.not-first')
                let note = jQuery('.panel-note')
                jQuery('.show-new').on('click', function() {
                    new_input.show()
                    not_new.hide()
                    note.empty()
                })
                jQuery('.show-send').on('click', function() {
                    send.show()
                    not_send.hide()
                    note.empty()
                })
                jQuery('.show-first').on('click', function() {
                    first.show()
                    not_first.hide()
                    note.empty()
                })

                // listen to buttons
                jQuery('#submit-new').on('click', function(){
                    self_register()
                })
                jQuery('#submit-send-link').on('click', function(){
                    retrieve()
                })

                function self_register() {
                    let spinner = jQuery('.loading-spinner')
                    spinner.addClass('active')

                    let submit_button = jQuery('#submit-stream')
                    submit_button.prop('disabled', true)

                    let honey = jQuery('#email').val()
                    if ( honey ) {
                        submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                        spinner.removeClass('active')
                        return;
                    }

                    let name_input = jQuery('#name')
                    let name = name_input.val()
                    if ( ! name ) {
                        jQuery('#name-error').show()
                        submit_button.removeClass('loading')
                        name_input.focus(function(){
                            jQuery('#name-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let email_input = jQuery('#e2')
                    let email = email_input.val()
                    if ( ! email ) {
                        jQuery('#email-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#email-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let post_type_name_input = jQuery('#post_type_name')
                    let post_type_name = post_type_name_input.val()
                    if ( ! post_type_name ) {
                        jQuery('#stream-name-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#stream-name-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let form_data = {
                        name: name,
                        email: email,
                        post_type_name: post_type_name
                    }

                    console.log(form_data)
                    let new_panel = jQuery('#new-panel')
                    window.api_sr( 'register', form_data )
                        .done(function(response){
                            console.log(response)

                            if ( response.status === 'EMAILED' ) {
                                new_panel.empty().html(`
                                Excellent! Check your email for a direct link to your stream portal.<br><br>
                              `)
                            }
                            else if ( response.status === 'CREATED' ) {
                                new_panel.empty().html(`
                                Excellent! You've been sent an email with your stream link. Please, complete your remaining community profile.<br><br>
                                <a class="button" href="${response.link}" target="_parent">Open Reporting Portal</a>
                              `)
                            }
                            // else if ( response.status === 'FAIL' ) {
                            //     new_panel.empty().html(`
                            //         Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                            //       `)
                            // }

                            jQuery('.loading-spinner').removeClass('active')
                            jQuery('.panel-note').empty()
                        })
                        .fail(function (e) {
                            new_panel.empty().html(`
                                    Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                                  `)
                        })

                }

                function retrieve(){
                    let spinner = jQuery('.loading-spinner')
                    spinner.addClass('active')

                    let submit_button = jQuery('#submit-send-link')
                    submit_button.prop('disabled', true)

                    let honey = jQuery('#email-send').val()
                    if ( honey ) {
                        submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                        spinner.removeClass('active')
                        return;
                    }

                    let email_input = jQuery('#e2-send')
                    let email = email_input.val()
                    if ( ! email ) {
                        jQuery('#email-error-send').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#email-error-send').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let form_data = {
                        email: email
                    }

                    window.api_sr( 'retrieve', form_data )
                        .done(function(response){
                            console.log(response)
                            if ( response ) {
                                jQuery('#send-panel').empty().html(`
                                    Excellent! Go to you email inbox and find your personal link.<br>
                                  `)
                                jQuery('.panel-note').empty()
                            } else {
                                jQuery('.new').show()
                                jQuery('.not-new').hide()
                                jQuery('.panel-note').html('Email not found. Please, register.')
                            }
                            jQuery('.loading-spinner').removeClass('active')

                        })
                }
            })
        </script>
        <?php
    }

    public function body_retrieve() {
        $this->javascript_object();
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <style>
            body { background-color:white; } #wrapper { max-width: 600px; margin: 1em auto; } #email { display:none !important; } #email-send { display:none !important; }
        </style>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell" id="content">
                    <div class="grid-x">
                        <div class="cell panel-note"></div>
                        <div class="cell">
                            <label for="email">Email</label>
                            <input type="email" id="email-send" name="email" placeholder="Email" />
                            <input type="email" id="e2-send" name="email" class="required" placeholder="Email" />
                            <span id="email-error-send" class="form-error">You're email is required.</span>
                        </div>
                        <div class="cell center">
                            <button class="button large" id="submit-send-link">Email me access link</button> <span class="loading-spinner"></span><br>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function(){

                // listen to buttons
                jQuery('#submit-send-link').on('click', function(){
                    retrieve()
                })

                function retrieve(){
                    let spinner = jQuery('.loading-spinner')
                    spinner.addClass('active')

                    let submit_button = jQuery('#submit-send-link')
                    submit_button.prop('disabled', true)

                    let honey = jQuery('#email-send').val()
                    if ( honey ) {
                        submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                        spinner.removeClass('active')
                        return;
                    }

                    let email_input = jQuery('#e2-send')
                    let email = email_input.val()
                    if ( ! email ) {
                        jQuery('#email-error-send').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#email-error-send').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let form_data = {
                        email: email
                    }

                    window.api_sr( 'retrieve', form_data )
                        .done(function(response){
                            console.log(response)
                            if ( response ) {
                                jQuery('#send-panel').empty().html(`
                                    Excellent! Go to you email inbox and find your personal link.<br>
                                  `)
                                jQuery('.panel-note').empty()
                            } else {
                                jQuery('.new').show()
                                jQuery('.not-new').hide()
                                jQuery('.panel-note').html('Email not found. Please, register.')
                            }
                            jQuery('.loading-spinner').removeClass('active')
                        })
                }
            })
        </script>
        <?php
    }

    public function body_join() {
        $this->javascript_object();
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <style>
            body { background-color:white; } #wrapper { max-width: 600px; margin: 1em auto; } #email { display:none !important; } #email-send { display:none !important; }
        </style>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell" id="content">
                    <input type="hidden" id="stream-grid-id" />
                    <div class="grid-x center">
                        <div class='cell'>
                            <h1>Your Invited to Report</h1><hr>
                            <p>You've been invited to report on your movement work as a contributing reporter for the <?php echo esc_html( get_the_title( $this->parts['post_id'] ) ) ?>.</p>
                            <hr>
                        </div>
                    </div>

                    <div class="grid-x">
                        <div class="cell">
                            <label for="name">Name *</label>
                            <input type="text" id="name" class="required" placeholder="Your name" />
                            <span id="name-error" class="form-error">You're name is required.</span>
                        </div>
                        <div class="cell">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" placeholder="Email" />
                            <input type="email" id="e2" name="email" class="required" placeholder="Your Email Address" />
                            <span id="email-error" class="form-error">You're email is required.</span>
                        </div>
                        <div class="cell center">
                            <button class="button large" id="submit-new">Join as Reporter</button> <span class="loading-spinner"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function(){

                // listen to buttons
                jQuery('#submit-new').on('click', function(){

                    let spinner = jQuery('.loading-spinner')
                    spinner.addClass('active')

                    let submit_button = jQuery('#submit-stream')
                    submit_button.prop('disabled', true)

                    let honey = jQuery('#email').val()
                    if ( honey ) {
                        submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                        spinner.removeClass('active')
                        return;
                    }

                    let name_input = jQuery('#name')
                    let name = name_input.val()
                    if ( ! name ) {
                        jQuery('#name-error').show()
                        submit_button.removeClass('loading')
                        name_input.focus(function(){
                            jQuery('#name-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let email_input = jQuery('#e2')
                    let email = email_input.val()
                    if ( ! email ) {
                        jQuery('#email-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#email-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let form_data = {
                        name: name,
                        email: email
                    }

                    window.api_sr( 'join', form_data )
                        .done(function(response){
                            console.log(response)

                            let new_panel = jQuery('#new-panel')
                            if ( response.status === 'EMAILED' ) {
                                location.href = response.url
                            }
                            else if ( response.status === 'CREATED' ) {
                                new_panel.empty().html(`
                                Excellent! You've been sent an email with your stream link. Please, complete your remaining community profile.<br><br>
                                <a class="button" href="${response.link}" target="_parent">Open Reporting Portal</a>
                              `)
                            }
                            else if ( response.status === 'FAIL' ) {
                                new_panel.empty().html(`
                                    Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                                  `)
                            }

                            jQuery('.loading-spinner').removeClass('active')
                            jQuery('.panel-note').empty()
                        })
                })
            })
        </script>
        <?php
    }

    public function body_new_child() {
        $this->javascript_object();
        DT_Mapbox_API::geocoder_scripts();
        ?>
        <style>
            body { background-color:white; } #wrapper { max-width: 600px; margin: 1em auto; } #email { display:none !important; } #email-send { display:none !important; }
        </style>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell" id="content">
                    <div class="grid-x center">
                        <div class='cell'>
                            <h1>Create a New Child Stream</h1><hr>
                            <p>You've been invited to create a new movement report connected to <?php echo esc_html( get_the_title( $this->parts['post_id'] ) ) ?>.</p>
                            <hr>
                        </div>
                    </div>
                    <div class="grid-x">
                        <div class="cell panel-note"></div>
                        <div class="cell">
                            <label for="post_type_name">Name, Nickname, or City of your movement *</label>
                            <input type="text" id="post_type_name" class="required" placeholder="stream name or nickname" />
                            <span id="post-name-error" class="form-error">You're steam name is required.</span>
                        </div>
                        <div class="cell">
                            <label for="name">Name *</label>
                            <input type="text" id="name" class="required" placeholder="Name" />
                            <span id="name-error" class="form-error">You're name is required.</span>
                        </div>
                        <div class="cell">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" placeholder="Email" />
                            <input type="email" id="e2" name="email" class="required" placeholder="Email" />
                            <span id="email-error" class="form-error">You're email is required.</span>
                        </div>

                        <div class="cell center">
                            <button class="button large" id="submit-new">Create New Stream</button> <span class="loading-spinner"></span><br>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function(){

                // listen to buttons
                jQuery('#submit-new').on('click', function(){

                    let spinner = jQuery('.loading-spinner')
                    spinner.addClass('active')

                    let submit_button = jQuery('#submit-stream')
                    submit_button.prop('disabled', true)

                    let honey = jQuery('#email').val()
                    if ( honey ) {
                        submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                        spinner.removeClass('active')
                        return;
                    }

                    let name_input = jQuery('#name')
                    let name = name_input.val()
                    if ( ! name ) {
                        jQuery('#name-error').show()
                        submit_button.removeClass('loading')
                        name_input.focus(function(){
                            jQuery('#name-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let email_input = jQuery('#e2')
                    let email = email_input.val()
                    if ( ! email ) {
                        jQuery('#email-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#email-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }
                    let post_type_name_input = jQuery('#post_type_name')
                    let post_type_name = post_type_name_input.val()
                    if ( ! post_type_name ) {
                        jQuery('#post-name-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#post-name-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let form_data = {
                        name: name,
                        email: email,
                        post_type_name: post_type_name
                    }

                    console.log(form_data)
                    window.api_sr( 'create_child', form_data )
                        .done(function(response){
                            console.log(response)

                            let new_panel = jQuery('#new-panel')
                            if ( response.status === 'EMAILED' ) {
                                location.href = response.url
                            }
                            else if ( response.status === 'FAIL' ) {
                                new_panel.empty().html(`
                                    Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                                  `)
                            }

                            jQuery('.loading-spinner').removeClass('active')
                            jQuery('.panel-note').empty()
                        })
                })
            })
        </script>
        <?php
    }
}
