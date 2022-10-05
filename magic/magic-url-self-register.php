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
        $post_type = sanitize_text_field( wp_unslash( $params['parts']['post_type'] ) );

        switch ( $action ) {
            case 'retrieve':
                return $this->retrieve( $params['data'] );
            case 'register':
                return $this->register( $params['data'], $post_type );

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
            dt_write_log('No identity found');
            return false;
        }
    }

    public function register( $data, $post_type )
    {

        if (!isset($data['name'], $data['email'] ) ) {
            return [
                'status' => 'FAIL',
                'error' => new WP_Error(__METHOD__, 'Missing parameter', ['status' => 400])
            ];
        }

        $display_name = sanitize_text_field(wp_unslash($data['name']));
        
        $phone = sanitize_text_field(wp_unslash($data['phone']));

        $post_type_name = sanitize_text_field(wp_unslash($data['post_type_name']));
        $user_email = sanitize_email(wp_unslash($data['email']));

        $identity = $this->build_identity($user_email);

        // has user_id and has streams
        if (isset($identity['post_type_ids']) && !empty($identity['post_type_ids'])) {
            $this->_send_to_user($identity);
            dt_write_log('has user_id and has streams');
            return [
                'status' => 'EMAILED'
            ];
        }

        // has a user id, but does not have a contact_id. This should not happen.
        if (!empty($identity['user_id']) && empty($identity['contact_ids'])) {
            return [
                'status' => 'FAIL',
                'message' => 'User ID exists but contact has not been created'
            ];
        }

        // has user_id but has no streams
        if (!empty($identity['user_id']) && empty($identity['post_type_ids'])) {
            $new_stream = $this->_create_post($identity, $post_type_name);
            if (is_wp_error($new_stream)) {
                return [
                    'status' => 'FAIL',
                    'error' => $new_stream
                ];
            }
            // rebuild identity
            $identity = $this->build_identity($user_email);
            $send_result = $this->_send_to_user($identity);
            return [
                'status' => 'EMAILED',
                'message' => $send_result
            ];
        }

        if (empty($identity['user_id'])) {

//            $exploded_email = explode( '@', $user_email );

//            $user_name = str_replace( ' ', '_', strtolower( $display_name ) );
//            $user_name_last = dt_create_field_key( dt_create_unique_key() );
//            $user_name = $user_name_first . $user_name_last;
//            if ( username_exists( $user_name ) ) {
//                $user_name = $user_name_first . dt_create_field_key( dt_create_unique_key() );
//            }

            $user_name = str_replace(' ', '_', strtolower($display_name));
            $user_roles = ['reporter'];

            $current_user = wp_get_current_user();
            $current_user->add_cap('create_users', true);
            $current_user->add_cap('access_contacts', true);
            $current_user->add_cap('create_contacts', true);
            $current_user->add_cap('update_any_contacts', true);
            $current_user->add_cap('create_streams', true);

            dt_write_log('$current_user');
            dt_write_log($current_user);
            dt_write_log($user_name);

            $contact_id = Disciple_Tools_Users::create_user($user_name, $user_email, $display_name, $user_roles);
            dt_write_log('$contact_id');
            dt_write_log($contact_id);

            $identity = $this->build_identity($user_email);
            $new_stream = $this->_create_post($identity, $post_type_name);
            if (is_wp_error($new_stream)) {
                return [
                    'status' => 'FAIL',
                    'error' => $new_stream
                ];
            }
            // rebuild identity
            $identity = $this->build_identity($user_email);
            $send_result = $this->_send_to_user($identity);
            return [
                'status' => 'EMAILED',
                'message' => $send_result
            ];


            // sanitize user form input
            $password = sanitize_text_field(wp_unslash($_POST['password']));
            $email = sanitize_email(wp_unslash($_POST['email']));
            $explode_email = explode('@', $email);
            if (isset($explode_email[0])) {
                $username = $explode_email[0];
            } else {
                $username = str_replace('@', '_', $email);
                $username = str_replace('.', '_', $username);
            }
            $username = sanitize_user($username);


            $display_name = $username;
            if (isset($_POST['display_name'])) {
                $display_name = trim(sanitize_text_field(wp_unslash($_POST['display_name'])));
            }

            if (email_exists($email)) {
                $error->add(__METHOD__, __('Sorry. This email is already registered. Try re-setting your password', 'location_grid'));
                return $error;
            }

            if (username_exists($username)) {
                $username = $username . rand(0, 9);
            }

            $userdata = [
                'user_email' => $email,
                'user_login' => $username,
                'display_name' => $display_name,
                'user_pass' => $password,
                'role' => $dt_custom_login['default_role'] ?? 'registered'
            ];

            $user_id = wp_insert_user($userdata);

            if (is_wp_error($user_id)) {
                $error->add(__METHOD__, __('Something went wrong. Sorry. Could you try again?', 'location_grid'));
                return $error;
            }

            if (is_multisite()) {
                add_user_to_blog(get_current_blog_id(), $user_id, 'subscriber'); // add user to site.
            }


        }
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
                    WHERE pm.meta_key LIKE 'contact_email%' AND pm.meta_value = %s
                    ORDER BY pms.meta_value ASC;
                ", $email ));
            if ( is_wp_error( $ids ) || empty( $ids ) ) {
                return [];
            }
            foreach( $ids as $id ) {
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
        if ( ! isset( $app_meta_key, $app_p2p_connection_type ) || empty( $app_meta_key ) || empty( $app_p2p_connection_type )  ) {
            return $post_objects;
        }

        foreach( $contact_ids as $contact_id ) {
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
            $message_plain_text = __('Follow this link to access your reporting portal.') . '

';
            foreach ($identity['post_type_ids'] as $post_object) {
                $link = trailingslashit( site_url()) . $this->app_url . $post_object['magic_key'];
                $message_plain_text .=
                    'Reporting Access for ' . $post_object['name'] . ':' . '

' . $link . '

';
            }

            $subject = __('Reports Access');
            return dt_send_email($identity['email'], $subject, $message_plain_text);
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
                "Source" => "This stream was self-registered."
            ]
        ];

        return DT_Posts::create_post( 'streams', $fields, true, false );
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
                    'add' => __( 'Zume', 'disciple-tools-reporting-app' ),
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
                                <button type="button" class="button large expanded show-new">Register a New Stream</button>
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
                                <label for="post_type_name">Name or nickname of your movement</label>
                                <input type="text" id="post_type_name" class="required" placeholder="stream name or nickname" />
                                <span id="stream-name-error" class="form-error">You're name is required.</span>
                            </div>
                            <div class="cell">
                                <label for="name">Your name</label>
                                <input type="text" id="name" class="required" placeholder="Name" />
                                <span id="name-error" class="form-error">You're name is required.</span>
                            </div>

                            <div class="cell">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email" />
                                <input type="email" id="e2" name="email" class="required" placeholder="Email" />
                                <span id="email-error" class="form-error">You're email is required.</span>
                            </div>
                            <div class="cell">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" class="required" placeholder="Phone" />
                                <span id="phone-error" class="form-error">You're phone is required.</span>
                            </div>
                            <div class="cell">
                                <label for="location">City or address</label>
                                <input type="text" id="location" name="location" placeholder="City or Address" />
                                <span id="phone-error" class="form-error"></span>
                            </div>
                            <div class="cell center">
                                <button class="button large" id="submit-new">Register</button> <span class="loading-spinner"></span><br>
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

                    let phone_input = jQuery('#phone')
                    let phone = phone_input.val()
                    if ( ! phone ) {
                        jQuery('#phone-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#phone-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let location_input = jQuery('#location')
                    let location = location_input.val()

                    let form_data = {
                        name: name,
                        email: email,
                        phone: phone,
                        location: location
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
                    form_data.post_type_name = post_type_name

                    console.log(form_data)
                    window.api_sr( 'register', form_data )
                        .done(function(response){
                            console.log(response)

                            let new_panel = jQuery('#new-panel')
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
                            else if ( response.status === 'FAIL' ) {
                                new_panel.empty().html(`
                                    Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                                  `)
                            }

                            jQuery('.loading-spinner').removeClass('active')
                            jQuery('.panel-note').empty()
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

    public function body_register_child() {
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
                                <button type="button" class="button large expanded show-new">Register a New Stream</button>
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
                                <label for="post_type_name">Name or nickname of your movement</label>
                                <input type="text" id="post_type_name" class="required" placeholder="stream name or nickname" />
                                <span id="stream-name-error" class="form-error">You're name is required.</span>
                            </div>
                            <div class="cell">
                                <label for="name">Your name</label>
                                <input type="text" id="name" class="required" placeholder="Name" />
                                <span id="name-error" class="form-error">You're name is required.</span>
                            </div>

                            <div class="cell">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email" />
                                <input type="email" id="e2" name="email" class="required" placeholder="Email" />
                                <span id="email-error" class="form-error">You're email is required.</span>
                            </div>
                            <div class="cell">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" class="required" placeholder="Phone" />
                                <span id="phone-error" class="form-error">You're phone is required.</span>
                            </div>
                            <div class="cell">
                                <label for="location">City or address</label>
                                <input type="text" id="location" name="location" placeholder="City or Address" />
                                <span id="phone-error" class="form-error"></span>
                            </div>
                            <div class="cell center">
                                <button class="button large" id="submit-new">Register</button> <span class="loading-spinner"></span><br>
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

                    let phone_input = jQuery('#phone')
                    let phone = phone_input.val()
                    if ( ! phone ) {
                        jQuery('#phone-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#phone-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let location_input = jQuery('#location')
                    let location = location_input.val()

                    let form_data = {
                        name: name,
                        email: email,
                        phone: phone,
                        location: location
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
                    form_data.post_type_name = post_type_name

                    console.log(form_data)
                    window.api_sr( 'register', form_data )
                        .done(function(response){
                            console.log(response)

                            let new_panel = jQuery('#new-panel')
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
                            else if ( response.status === 'FAIL' ) {
                                new_panel.empty().html(`
                                    Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                                  `)
                            }

                            jQuery('.loading-spinner').removeClass('active')
                            jQuery('.panel-note').empty()
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
                            <label for="name">Your name *</label>
                            <input type="text" id="name" class="required" placeholder="Name" />
                            <span id="name-error" class="form-error">You're name is required.</span>
                        </div>
                        <div class="cell">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" placeholder="Email" />
                            <input type="email" id="e2" name="email" class="required" placeholder="Email" />
                            <span id="email-error" class="form-error">You're email is required.</span>
                        </div>
                        <div class="cell">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" class="required" placeholder="Phone" />
                            <span id="phone-error" class="form-error">You're phone is required.</span>
                        </div>
                        <div class="cell">
                            <label for="location">City or address</label>
                            <input type="text" id="location" name="location" placeholder="City or Address" />
                            <span id="phone-error" class="form-error"></span>
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
                    self_register()
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

                    let phone_input = jQuery('#phone')
                    let phone = phone_input.val()
                    if ( ! phone ) {
                        jQuery('#phone-error').show()
                        submit_button.removeClass('loading')
                        email_input.focus(function(){
                            jQuery('#phone-error').hide()
                        })
                        submit_button.prop('disabled', false)
                        spinner.removeClass('active')
                        return;
                    }

                    let location_input = jQuery('#location')
                    let location = location_input.val()

                    let form_data = {
                        name: name,
                        email: email,
                        phone: phone,
                        location: location
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
                    form_data.post_type_name = post_type_name

                    console.log(form_data)
                    
                    window.api_sr( 'register', form_data )
                        .done(function(response){
                            console.log(response)

                            let new_panel = jQuery('#new-panel')
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
                            else if ( response.status === 'FAIL' ) {
                                new_panel.empty().html(`
                                    Oops. Something went wrong. Please, refresh and try again. <a onclick="location.reload();">reload</a>
                                  `)
                            }

                            jQuery('.loading-spinner').removeClass('active')
                            jQuery('.panel-note').empty()
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

}
