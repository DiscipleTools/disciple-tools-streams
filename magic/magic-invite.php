<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Streams_Invite_To_Join_Stream extends DT_Magic_Url_Base {

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

        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );

        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        if ( !$this->check_parts_match() ){
            return;
        }

        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'body' ] ); // body for no post key
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
    }

    public function wp_enqueue_scripts(){
        DT_Mapbox_API::load_mapbox_header_scripts();
        DT_Mapbox_API::load_mapbox_search_widget();
    }

    public function header_javascript(){
        ?>
        <script>
            let jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'mirror_url' => dt_get_location_grid_mirror( true ),
                'theme_uri' => trailingslashit( get_stylesheet_directory_uri() ),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'title' => get_the_title( $this->parts['post_id'] ),
                'post_type' => $this->post_type,
                'trans' => [
                    'add' => __( 'Zume', 'disciple-tools-reporting-app' ),
                ],
            ]) ?>][0]
            jQuery(document).ready(function($){
                window.api_post = ( action, data ) => {
                    return $.ajax({
                        type: "POST",
                        data: JSON.stringify({ action: action, parts: jsObject.parts, data: data }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
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
                        <div>
                            <div class="grid-x center">
                                <div class='cell'>
                                    <h1>Your Invited to Report on ${jsObject.title}</h1><hr>
                                    <p>You've been invited to report on your movement work as a contributing reporter for the ${jsObject.title} stream.</p>
                                    <hr>
                                </div>
                            </div>
                            <div class="grid-x">
                                <div class="cell panel-note"></div>
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
                                    <input type="tel" id="phone" name="phone" placeholder="Phone" />
                                </div>
                                <div class="cell">
                                    <label for="location">City or address</label>
                                    <input type="text" id="location" name="location" placeholder="City or Address" />
                                    <span id="phone-error" class="form-error"></span>
                                </div>
                                <div class="cell center">
                                    <button class="button large" id="submit-new">Join as a Reporter</button> <span class="loading-spinner"></span><br>
                                </div>
                            </div>
                        </div>
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
                    `)

                    // listen to buttons
                    $('#submit-new').on('click', function(){
                        join()
                    })
                } // end function
                build_modal()

                function join() {
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

                    let location_input = $('#location')
                    let location = location_input.val()

                    let form_data = {
                        name: name,
                        email: email,
                        phone: phone,
                        location: location
                    }
                    console.log(form_data)

                    window.api_post( 'register', form_data )
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
                        })
                }
            })
        </script>
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


    public function add_endpoints() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/' . $this->type, [
                [
                    'methods'  => 'POST',
                    'callback' => [ $this, 'endpoints' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function endpoints( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'],  $params['action'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );

        $action = sanitize_text_field( wp_unslash( $params['action'] ) );

        switch ( $action ) {
            case 'register':
                return $this->register( $params );
            default:
                return new WP_Error( __METHOD__, "Missing valid action", [ 'status' => 400 ] );
        }

    }

    public function register( $params ) {



        return $params;
    }

}
DT_Streams_Invite_To_Join_Stream::instance();


class DT_Streams_Invite_To_Create_Child_Stream extends DT_Magic_Url_Base {

    public $magic = false;
    public $parts = false;
    public $page_title = 'Invite to Create a Child Stream';
    public $page_description = 'Invite to Create a Child Stream';
    public $root = 'streams_app';
    public $type = 'create_child';
    public $post_type = 'streams';
    private $meta_key = '';
    public $show_bulk_send = false;
    public $show_app_tile = true;

    private static $_instance = null;
    public $meta = [];

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
        parent::__construct();

        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );

        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        if ( !$this->check_parts_match() ){
            return;
        }

        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'body' ] ); // body for no post key
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );

    }

    public function wp_enqueue_scripts(){
        DT_Mapbox_API::load_mapbox_header_scripts();
        DT_Mapbox_API::load_mapbox_search_widget();
    }

    public function header_javascript(){
        ?>
        <script>
            let jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'mirror_url' => dt_get_location_grid_mirror( true ),
                'theme_uri' => trailingslashit( get_stylesheet_directory_uri() ),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'title' => get_the_title( $this->parts['post_id'] ),
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
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
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

                        <div id="new-panel" class="new not-first not-send">
                            <div class="grid-x center">
                                <div class='cell'>
                                    <h2>Create a New Stream</h2>
                                    <p>You've been invited to report on your movement work as a stream of ${jsObject.title}</p>
                                    <hr>
                                </div>
                            </div>
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
                                    <button class="button large" id="submit-new">Create Stream</button> <span class="loading-spinner"></span><br>
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
                }
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

    public function add_endpoints() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/' . $this->type, [
                [
                    'methods'  => 'POST',
                    'callback' => [ $this, 'register' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function register( WP_REST_Request $request ) {
        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );

        return $params;
    }

}
DT_Streams_Invite_To_Create_Child_Stream::instance();
