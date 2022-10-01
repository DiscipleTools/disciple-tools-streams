<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

abstract class DT_Magic_Url_Self_Register extends DT_Magic_Url_Base
{

    public function __construct() {
        parent::__construct();
        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );
    }

    public function add_endpoints() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace,
            '/'.$this->type.'/self_register',
            [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function endpoint( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );
        $action = sanitize_text_field( wp_unslash( $params['action'] ) );

        switch ( $action ) {
            case 'register':
                return $this->register( $params['data'] );
            case 'retrieve':
                return $this->retrieve( $params['data'] );

            default:
                return new WP_Error( __METHOD__, "Missing valid action", [ 'status' => 400 ] );
        }
    }

        public function register_form_header_javascript() {
            ?>
            <script>
                let jsObject = [<?php echo json_encode([
                    'map_key' => DT_Mapbox_API::get_key(),
                    'mirror_url' => dt_get_location_grid_mirror( true ),
                    'theme_uri' => trailingslashit( get_stylesheet_directory_uri() ),
                    'root' => esc_url_raw( rest_url() ),
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                    'parts' => $this->parts,
                    'post_type' => $this->post_type,
                    'trans' => [
                        'add' => __( 'Zume', 'disciple-tools-reporting-app' ),
                    ],
                ]) ?>][0]
                jQuery(document).ready(function($){
                    window.new_stream = ( action, data ) => {
                        return $.ajax({
                            type: "POST",
                            data: JSON.stringify({ action: action, parts: jsObject.parts, data: data }),
                            contentType: "application/json; charset=utf-8",
                            dataType: "json",
                            url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/self_register',
                            beforeSend: function (xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                            }
                        })
                            .fail(function(e) {
                                $('#error').html(e)
                            })
                    }

                    function build_modal(){
                        // add html
                        $('#content').empty().html(`
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
                                    <label for="stream_name">Name or nickname of your movement</label>
                                    <input type="text" id="stream_name" class="required" placeholder="stream name or nickname" />
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
                                    <button class="button" id="submit-new">Register</button> <span class="loading-spinner"></span><br>
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
                                    <button class="button" id="submit-send-link">Email me access link</button> <span class="loading-spinner"></span><br>
                                    <a class="show-first">back</a>
                                </div>
                            </div>
                        </div>
                    `)

                        // set listeners
                        let send = $('.send')
                        let not_send = $('.not-send')
                        let new_input = $('.new')
                        let not_new = $('.not-new')
                        let first = $('.first')
                        let not_first = $('.not-first')
                        let note = $('.panel-note')
                        $('.show-new').on('click', function() {
                            new_input.show()
                            not_new.hide()
                            note.empty()
                        })
                        $('.show-send').on('click', function() {
                            send.show()
                            not_send.hide()
                            note.empty()
                        })
                        $('.show-first').on('click', function() {
                            first.show()
                            not_first.hide()
                            note.empty()
                        })

                        // listen to buttons
                        $('#submit-new').on('click', function(){
                            create_streamer()
                        })
                        $('#submit-send-link').on('click', function(){
                            retrieve_link_to_streamer()
                        })
                    } // end function
                    build_modal()


                    function create_streamer() {
                        let spinner = $('.loading-spinner')
                        spinner.addClass('active')

                        let submit_button = $('#submit-stream')
                        submit_button.prop('disabled', true)

                        let honey = $('#email').val()
                        if ( honey ) {
                            submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                            spinner.removeClass('active')
                            return;
                        }

                        let name_input = $('#name')
                        let name = name_input.val()
                        if ( ! name ) {
                            $('#name-error').show()
                            submit_button.removeClass('loading')
                            name_input.focus(function(){
                                $('#name-error').hide()
                            })
                            submit_button.prop('disabled', false)
                            spinner.removeClass('active')
                            return;
                        }

                        let email_input = $('#e2')
                        let email = email_input.val()
                        if ( ! email ) {
                            $('#email-error').show()
                            submit_button.removeClass('loading')
                            email_input.focus(function(){
                                $('#email-error').hide()
                            })
                            submit_button.prop('disabled', false)
                            spinner.removeClass('active')
                            return;
                        }

                        let phone_input = $('#phone')
                        let phone = phone_input.val()
                        if ( ! phone ) {
                            $('#phone-error').show()
                            submit_button.removeClass('loading')
                            email_input.focus(function(){
                                $('#phone-error').hide()
                            })
                            submit_button.prop('disabled', false)
                            spinner.removeClass('active')
                            return;
                        }

                        let stream_name_input = $('#stream_name')
                        let stream_name = stream_name_input.val()
                        if ( ! stream_name ) {
                            $('#stream-name-error').show()
                            submit_button.removeClass('loading')
                            email_input.focus(function(){
                                $('#stream-name-error').hide()
                            })
                            submit_button.prop('disabled', false)
                            spinner.removeClass('active')
                            return;
                        }

                        let location_input = $('#location')
                        let location = location_input.val()
                        // if ( ! location ) {
                        //   $('#location-error').show()
                        //   submit_button.removeClass('loading')
                        //   email_input.focus(function(){
                        //     $('#location-error').hide()
                        //   })
                        //   submit_button.prop('disabled', false)
                        //   spinner.removeClass('active')
                        //   return;
                        // }


                        let form_data = {
                            name: name,
                            email: email,
                            phone: phone,
                            stream_name: stream_name,
                            location: location
                        }
                        console.log(form_data)

                        window.new_stream( 'register', form_data )
                            .done(function(response){
                                console.log(response)
                                let new_panel = $('#new-panel')
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

                                $('.loading-spinner').removeClass('active')
                                $('.panel-note').empty()
                            })
                    }

                    function retrieve_link_to_streamer(){
                        let spinner = $('.loading-spinner')
                        spinner.addClass('active')

                        let submit_button = $('#submit-send-link')
                        submit_button.prop('disabled', true)

                        let honey = $('#email-send').val()
                        if ( honey ) {
                            submit_button.html('Shame, shame, shame. We know your name ... ROBOT!').prop('disabled', true )
                            spinner.removeClass('active')
                            return;
                        }

                        let email_input = $('#e2-send')
                        let email = email_input.val()
                        if ( ! email ) {
                            $('#email-error-send').show()
                            submit_button.removeClass('loading')
                            email_input.focus(function(){
                                $('#email-error-send').hide()
                            })
                            submit_button.prop('disabled', false)
                            spinner.removeClass('active')
                            return;
                        }

                        let form_data = {
                            email: email
                        }

                        window.new_stream( 'retrieve', form_data )
                            .done(function(response){
                                console.log(response)
                                if ( response ) {
                                    $('#send-panel').empty().html(`
                                        Excellent! Go to you email inbox and find your personal link.<br>
                                      `)
                                    $('.panel-note').empty()
                                } else {
                                    $('.new').show()
                                    $('.not-new').hide()
                                    $('.panel-note').html('Email not found. Please, register.')
                                }
                                $('.loading-spinner').removeClass('active')

                            })
                    }
                })
            </script>
            <style>
                body {
                    background-color:white;
                }
                #wrapper {
                    max-width: 600px;
                    margin: 1em auto;
                }
                #email {
                    display:none !important;
                }
                #email-send {
                    display:none !important;
                }
            </style>
            <?php
        }

        public function body(){
            DT_Mapbox_API::geocoder_scripts();
            ?>
            <div id="wrapper">
                <div class="grid-x">
                    <div class="cell" id="content"></div>
                </div>
            </div>
            <?php
        }

        public function retrieve( $data ) {
            // test if valid email
            if ( ! isset( $data['email'] ) || empty( $data['email'] ) ) {
                return new WP_Error( __METHOD__, 'email not set', [ 'status' => 400 ] );
            }

            $email = sanitize_email( wp_unslash( $data['email'] ) );

            $identity = $this->_build_identity( $email );
            if ( isset( $identity['stream_ids'] ) && ! empty( $identity['stream_ids'] ) ) {
                $this->_send_to_user( $identity );
                return true;
            } else {
                dt_write_log('No identity found');
                return false;
            }
        }

        public function register( $data )
        {

            if (!isset($data['name'], $data['phone'], $data['email'], $data['stream_name'])) {
                return [
                    'status' => 'FAIL',
                    'error' => new WP_Error(__METHOD__, 'Missing parameter', ['status' => 400])
                ];
            }

            $display_name = sanitize_text_field(wp_unslash($data['name']));
            $phone = sanitize_text_field(wp_unslash($data['phone']));

            $stream_name = sanitize_text_field(wp_unslash($data['stream_name']));
            $user_email = sanitize_email(wp_unslash($data['email']));

            $identity = $this->_build_identity($user_email);

            // has user_id and has streams
            if (isset($identity['stream_ids']) && !empty($identity['stream_ids'])) {
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
            if (!empty($identity['user_id']) && empty($identity['stream_ids'])) {
                $new_stream = $this->_create_stream($identity, $stream_name);
                if (is_wp_error($new_stream)) {
                    return [
                        'status' => 'FAIL',
                        'error' => $new_stream
                    ];
                }
                // rebuild identity
                $identity = $this->_build_identity($user_email);
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

                $identity = $this->_build_identity($user_email);
                $new_stream = $this->_create_stream($identity, $stream_name);
                if (is_wp_error($new_stream)) {
                    return [
                        'status' => 'FAIL',
                        'error' => $new_stream
                    ];
                }
                // rebuild identity
                $identity = $this->_build_identity($user_email);
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





        public function _send_to_user( $identity ) {
            dt_write_log(__METHOD__);
            dt_write_log( $identity );

            if ( isset( $identity['stream_ids'], $identity['email'] ) ) {
                $message_plain_text = __('Follow this link to access your reporting portal. Please, complete your remaining community profile.') . '

';
                foreach ($identity['stream_ids'] as $stream) {
                    $link = trailingslashit(site_url()) . $this->portal_url . $stream['magic_key'];
                    $message_plain_text .=
                        'Reporting Access for ' . $stream['name'] . ':' . '

' . $link . '

';
                }

                $subject = __('Reports Access');
                return dt_send_email($identity['email'], $subject, $message_plain_text);
            }
        }

        public function _create_stream( $identity, $stream_name ) {
            if ( isset( $identity['contact_ids'][0] ) ) {
                $contact_id = $identity['contact_ids'][0];
            } else {
                return new WP_Error( __METHOD__, 'No contact id set', [ 'status' => 400 ] );
            }
            $magic = new DT_Magic_URL( 'streams_app' );
            $fields = [
                'title' => $stream_name,
                'reporter' => [
                    'values' => [
                        [ 'value' => $contact_id ]
                    ],
                ],
                'streams_app_report_magic_key' => $magic->create_unique_key(),
                "notes" => [
                    "Source" => "This stream was self-registered."
                ]
            ];

            return DT_Posts::create_post( 'streams', $fields, true, false );
        }

        public function _build_identity( $email ) {
            dt_write_log(__METHOD__);

            $user_id = $this->_query_for_user_id( $email ); // int|false

            $contact_ids = $this->_query_for_contact_ids( $user_id, $email ); // id array | empty array

            $stream_ids = $this->_query_for_streams( $contact_ids );

            return [
                'user_id' => $user_id,
                'email' => $email,
                'contact_ids' => $contact_ids,
                'stream_ids' => $stream_ids,
            ];
        }

        public function _query_for_user_id( $email ) {
            global $wpdb;
            $id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID as user_id
                    FROM $wpdb->users
                    WHERE user_email = %s;
                    ", $email ) );
            if ( is_wp_error( $id ) ) {
                return false;
            }
            return $id;
        }

        public function _query_for_contact_ids( $user_id, $email ) {
            global $wpdb;
            $contact_ids = [];
            if ( $user_id ) {
                $meta_key = $wpdb->prefix . 'corresponds_to_contact';
                $id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT meta_value as contact_id
                        FROM $wpdb->usermeta
                        WHERE user_id = %d AND meta_key = %s;
                    ", $user_id, $meta_key ) );
                if ( is_wp_error( $id ) ) {
                    return [];
                }
                $contact_ids[] = $id;
                return $contact_ids;
            } else {
                // @todo prioritize active contact to the top 0 position
                $ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT pm.post_id 
                        FROM $wpdb->postmeta pm
                        JOIN $wpdb->posts p ON p.ID=pm.post_id AND p.post_type = 'contacts'
                        WHERE pm.meta_key LIKE 'contact_email%' AND pm.meta_value = %s;
                    ", $email ));
                if ( is_wp_error( $ids ) ) {
                    return [];
                }
                foreach( $ids as $id ) {
                    $contact_ids[] = $id;
                }
                return $contact_ids;
            }
        }

        public function _query_for_streams( $contact_ids ) {
            global $wpdb;
            if ( empty( $contact_ids ) || ! is_array( $contact_ids ) ) {
                return [];
            }
            $streams = [];
            foreach( $contact_ids as $contact_id ) {
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT %d as contact_id, p2p_from as stream_id, meta_value as magic_key, post_title as name
                        FROM $wpdb->p2p p2
                        LEFT JOIN $wpdb->postmeta pm ON pm.post_id=p2.p2p_from AND pm.meta_key = 'streams_app_report_magic_key'
                        LEFT JOIN $wpdb->posts p ON p.ID=pm.post_id
                        WHERE p2.p2p_type = 'streams_to_reporter' AND p2.p2p_to = %d;
                    ", $contact_id, $contact_id ), ARRAY_A );
                if ( is_wp_error( $results ) ) {
                    continue;
                }

                $streams = array_merge( $streams, $results );
            }
            return $streams;

        }

        public function _query_for_identity( $email ) {
            global $wpdb;

            // parse via user search
            $user_object = get_user_by('email', $email );
            if ( $user_object ) {
                $contact_id = Disciple_Tools_Users::get_contact_for_user( $user_object->ID );
                if ( is_wp_error( $contact_id ) ) {
                    return $contact_id;
                }
                else if ( ! empty( $contact_id ) ) {
                    // happy path
                    return [
                        'user_id' => $user_object->ID,
                        'contact_id' => $contact_id,
                        'email' => $email
                    ];
                }
                else {
                    // is a user but has no contact record ?????
                }
            }

            // parse via contact search
            $connected_contacts = $wpdb->get_results("
            SELECT DISTINCT p.ID as post_id, pm.meta_value as email, pmu.meta_value as user_id
            FROM $wpdb->posts p
            JOIN $wpdb->p2p p2 ON p2.p2p_to=p.ID AND p2.p2p_type = 'streams_to_reporter'
            JOIN $wpdb->postmeta pm ON pm.post_id=p.ID AND pm.meta_key LIKE 'contact_email%' AND pm.meta_key NOT LIKE '%_details'
            LEFT JOIN $wpdb->postmeta pmu ON p.ID=pmu.post_id AND pmu.meta_key = 'corresponds_to_user'
            WHERE p.post_type = 'contacts'
        ");

        }

        /**
         * @param $root
         * @param $type
         * @param $email
         * @return false|array
         */
        public function _search_for_email( $email ) {
            global $wpdb;

            // is this a user
            // does this user have a contact
            // is this contact connected to a number of streams
            // what are the magic links connected to the streams

            $connected_contacts = $wpdb->get_results("
            SELECT DISTINCT p.ID as post_id, pm.meta_value as email, pmu.meta_value as user_id
            FROM $wpdb->posts p
            JOIN $wpdb->p2p p2 ON p2.p2p_to=p.ID AND p2.p2p_type = 'streams_to_reporter'
            JOIN $wpdb->postmeta pm ON pm.post_id=p.ID AND pm.meta_key LIKE 'contact_email%' AND pm.meta_key NOT LIKE '%_details'
            LEFT JOIN $wpdb->postmeta pmu ON p.ID=pmu.post_id AND pmu.meta_key = 'corresponds_to_user'
            WHERE p.post_type = 'contacts'
        ");

            // search users
            $user_object = get_user_by('email', $email );
            if ( $user_object ) {
                if ( is_multisite() ) {
                    // check if user is part of multi site, if not added them with reporting_only permissions
                }

                // check if user has contact record, if not create contact record
                $contact_id = Disciple_Tools_Users::get_contact_for_user( $user_object->ID );
                dt_write_log('$contact_id');
                dt_write_log($contact_id);
                // check if user has role reporting_only or greater


            }
            // else test if a contact record exists with the email or phone provided
            else {
                $record_post_id = $wpdb->get_results($wpdb->prepare( "
                SELECT pm.post_id, pm1.meta_value as status
                FROM $wpdb->postmeta pm
                JOIN $wpdb->posts p ON p.ID=pm.post_id AND p.post_type = 'contacts'
                LEFT JOIN $wpdb->postmeta pm1 ON pm.post_id = pm1.post_id AND pm1.meta_key = 'overall_status'
                LEFT JOIN $wpdb->postmeta pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key LIKE %s
                WHERE  pm.meta_value = %s
            ", $wpdb->esc_like('contact_email').'%', $email ), ARRAY_A );
                if ( is_wp_error( $record_post_id ) && empty( $record_post_id ) ) {
                    return false;
                }
            }

//        $search_query = [
//            'text' => $email
//        ];
//        $search_query = json_encode($search_query);
//        $contacts = DT_Posts::advanced_search( $search_query, 'contacts', 0 );
//        dt_write_log($contacts);

            // search contacts
            return false;
            // empty or error

            // found 1 match
            if ( count( $record_post_id ) === 1 ) {
                return $record_post_id[0];
            }
            // found more than 1 match
            else {
                foreach ( $record_post_id as $row ) {
                    if ( in_array( $row['status'], [ 'active', 'reporting_only' ] ) ) {
                        return $row;
                    }
                }
                return false;
            }
        }
    }
