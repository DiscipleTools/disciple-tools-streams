<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Stream_Reports extends DT_Magic_Url_Base
{
    public $post_type = 'streams';
    public $magic = false;
    public $parts = false;
    public $page_title = 'Stream Report';
    public $page_description = 'Stream Report';
    public $root = "streams_app"; // define the root of the url {yoursite}/root/type/key/action
    public $type = 'report'; // define the type
    public $type_name = 'Stream Report';
    private $meta_key = '';
    public $magic_url;
    public $has_children = false;
    public $show_bulk_send = true;
    public $show_app_tile = true; // show this magic link in the Apps tile on the post record
    public $type_actions = [
        '' => 'Home',
        'manage' => 'Add / Edit',
        'stats' => 'Summary',
        'maps' => 'Map',
        'children' => 'Child Streams',
        'invite' => 'Send Invite',
    ];

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
        // register tiles if on details page
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 20, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 30, 2 );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 50, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'tile_scripts' ], 100 );

        // register REST and REST access
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_filter( 'dt_custom_fields_settings', [ $this, 'custom_fields' ], 10, 2 );

        // fail if not valid url
        $this->parts = $this->magic->parse_url_parts();
        if ( ! $this->parts ){
            return;
        }

        // fail if does not match type
        if ( $this->type !== $this->parts['type'] ){
            return;
        }

        $this->magic_url = site_url() . '/' . $this->parts['root'] . '/' . $this->parts['type'] . '/' . $this->parts['public_key'] . '/';

        $this->has_children = $this->_has_children( $this->parts['post_id'] );

        // load if valid url

        if ( $this->magic->is_valid_key_url( $this->type ) && 'stats' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'stats_body' ] );
        }
        else if ( $this->magic->is_valid_key_url( $this->type ) && 'maps' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'maps_body' ] );
        }
        else if ( $this->magic->is_valid_key_url( $this->type ) && 'manage' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'manage_body' ] );
        }
        else if ( $this->magic->is_valid_key_url( $this->type ) && 'invite' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'invite_body' ] );
        }
        else if ( $this->magic->is_valid_key_url( $this->type ) && 'children' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'children_body' ] );
        }
        else if ( $this->magic->is_valid_key_url( $this->type ) && '' === $this->parts['action'] ) {
            add_action( 'dt_blank_body', [ $this, 'home_body' ] );
        } else {
            // fail if no valid action url found
            return;
        }

        add_action( 'dt_blank_head', [ $this, '_header' ] );
        add_action( 'dt_blank_footer', [ $this, '_footer' ] );

        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, '_wp_enqueue_scripts' ], 100 );
    }

    public function dt_custom_fields_settings( $fields, $post_type ) {
        if ( $post_type === "contacts" ) {
            if (isset( $fields["overall_status"] ) && !isset( $fields["overall_status"]["default"]["reporting_only"] )) {
                $fields["overall_status"]["default"]["reporter"] = [
                    'label' => 'Reporting Only',
                    'description' => 'Contact is a reporting practitioner.',
                    'color' => '#F43636'
                ];
            }
            if (isset( $fields["sources"] ) && !isset( $fields["sources"]["default"]["self_registered_reporter"] )) {
                $fields["sources"]["default"]["self_registered_reporter"] = [
                    'label' => 'Self-Registered Reporter',
                    'key' => 'self_registered_reporter',
                    'type' => 'other',
                    'description' => 'Contact came from self-registration portal as a reporter.',
                    'enabled' => 1
                ];
            }
        }
        return $fields;
    }

    public function dt_details_additional_tiles( $tiles, $post_type = "" ){
        if ( $post_type === 'streams' ){
            $tiles["reports"] = [ "label" => __( "Reports", 'disciple-tools-streams' ) ];
            $tiles["child_reports"] = [ "label" => __( "Child Reports", 'disciple-tools-streams' ) ];
        }
        return $tiles;
    }

    public function dt_details_additional_section( $section, $post_type ) {
        // test if streams post type and streams_app_module enabled
        if ( $post_type === 'streams' ) {

            // reports tile
            if ( $section === "reports" ){

                $magic = new DT_Magic_URL( 'streams_app' );
                $types = $magic->list_types();

                // types
                if ( isset( $types['report'], $types['report']['root'], $types['report']['type'] ) ) {

                    $reports = self::instance()->statistics_reports( get_the_ID() );
                    /**
                     * Button Controls
                     */
                    ?>
                    <div class="cell" id="<?php echo esc_attr( $types['report']['root'] ) ?>-<?php echo esc_attr( $types['report']['type'] ) ?>-wrapper"></div>
                    <?php
                    /**
                     * List Reports
                     */
                    if ( ! empty( $reports ) ) {
                        foreach ( $reports as $year => $report ){
                            ?>
                            <div class="section-subheader">
                                Reports in <?php echo esc_html( $year ) ?>
                            </div>
                            <div class="reports-for-<?php echo esc_html( $year ) ?>">
                                <div class="grid-x">
                                    <div class="cell small-6"><?php echo esc_html__( 'Total Baptisms', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_baptisms'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Total Disciples', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_disciples'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Total Churches', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_churches'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Countries', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_countries'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'States', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_states'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Counties', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_counties'] ) ?></div>
                                </div>
                            </div>
                            <div><hr>
                                <a class="button hollow" id="<?php echo esc_attr( $types['report']['root'] ) ?>-<?php echo esc_attr( $types['report']['type'] ) ?>-manage-reports">full reports</a>
                            </div>
                            <?php
                            break; // loop only the most recent year
                        }
                    } else {
                        ?>
                        <div class="section-subheader">
                            No Reports
                        </div>
                        <?php
                    }
                    ?>
                    <?php
                }
            } /* end stream/app if*/

            // reports tile
            if ( $section === "child_reports" ){

                $magic = new DT_Magic_URL( 'streams_app' );
                $types = $magic->list_types();

                // types
                if ( isset( $types['report'], $types['report']['root'], $types['report']['type'] ) ) {
                    $post_id = get_the_ID();
                    $reports = self::instance()->statistics_reports( (string) $post_id, true );
                    /**
                     * Button Controls
                     */
                    ?>
                    <div class="cell" id="<?php echo esc_attr( $types['report']['root'] ) ?>-<?php echo esc_attr( $types['report']['type'] ) ?>-wrapper"></div>
                    <?php
                    /**
                     * List Reports
                     */
                    if ( ! empty( $reports ) ) {
                        foreach ( $reports as $year => $report ){
                            ?>
                            <div class="section-subheader">
                                Reports in <?php echo esc_html( $year ) ?>
                            </div>
                            <div class="reports-for-<?php echo esc_html( $year ) ?>">
                                <div class="grid-x">
                                    <div class="cell small-6"><?php echo esc_html__( 'Total Baptisms', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_baptisms'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Total Disciples', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_disciples'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Total Churches', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_churches'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Countries', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_countries'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'States', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_states'] ) ?></div>
                                    <div class="cell small-6"><?php echo esc_html__( 'Counties', 'disciple-tools-streams' ) ?></div><div class="cell small-6"><?php echo esc_html( $report['total_counties'] ) ?></div>
                                </div>
                            </div>
                            <div><hr>
                                <a class="button hollow" id="<?php echo esc_attr( $types['report']['root'] ) ?>-<?php echo esc_attr( $types['report']['type'] ) ?>-manage-child-reports">full reports</a>
                            </div>
                            <?php
                            break; // loop only the most recent year
                        }
                    } else {
                        ?>
                        <div class="section-subheader">
                            No Reports
                        </div>
                        <?php
                    }
                    ?>
                    <?php
                }
            } /* end stream/app if*/

        } // end if streams and enabled
    }

    public function tile_scripts(){
        if ( is_singular( "streams" ) ){
            $magic = new DT_Magic_URL( 'streams_app' );
            $types = $magic->list_types();
            $report = $types['report'] ?? [];
            $report['new_key'] = $magic->create_unique_key();

            wp_localize_script( // add object to streams-post-type.js
                'dt_streams', 'streams_report_module', [
                    'report' => $report,
                ]
            );
        }
    }

    public function custom_fields( $fields, $post_type ){
        if ( $post_type === 'streams' ){
            // do action
            $fields[$this->root . '_' . $this->type . '_magic_key'] = [
                'name'   => 'Private Report Key',
                'description' => '',
                'type'   => 'hash',
                'hidden' => true,
            ];
            $fields['report_last_modified'] = [
                'name'   => 'Last Report',
                'description' => 'Stores the time of the last insert or delete performed on reports.',
                'type' => 'date',
                'default' => '',
                'show_in_table' => true
            ];
        }
        return $fields;
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'mapbox-gl';
        $allowed_js[] = 'mapbox-cookie';
        $allowed_js[] = 'mapbox-search-widget';
        $allowed_js[] = 'google-search-widget';
        $allowed_js[] = 'jquery-cookie';
        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        $allowed_css[] = 'mapbox-gl-css';
        return $allowed_css;
    }

    public function header_style(){
        ?>
        <style>
            body {
                background: white;
            }
            #title {
                font-size:2rem;
                font-weight: 100;
                width:100%;
                text-align:center;
            }
            #top-bar {
                position:relative;
                padding-bottom:1em;
            }
            #menu-icon {
                color: black;
                position:absolute:
                left: .5em;
                top: .5em;
            }
            #add-new {
                padding-top:1em;
            }
            #top-loader {
                position:absolute;
                right:5px;
                top: 5px;
            }
            #wrapper {
                max-width:500px;
                margin:0 auto;
                padding: .5em;
                background-color: white;
            }
            .add-number-input {
                width:50px;
                display:inline;
            }
            #mapbox-search {
                padding:5px 10px;
                border-bottom-color: rgb(138, 138, 138);
            }
            .year-input {
                width:100px;
                display:inline;
            }
            #new-report-form {
                padding: 1em .5em;
                background-color: #f4f4f4;;
                border: 1px solid #3f729b;
                font-weight: bold;
            }
            .number-input {
                border-top: 0;
                border-left: 0;
                border-right: 0;
                border-bottom: 1px solid gray;
                box-shadow: none;
                background: white;
                text-align:center;
            }
            .stat-heading {
                font-size: 2rem;
            }
            .stat-number {
                font-size: 3.5rem;
            }
            .stat-year {
                font-size: 2rem;
                color: darkgrey;
            }
            /* Chrome, Safari, Edge, Opera */
            input::-webkit-outer-spin-button,
            input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            /* Firefox */
            input[type=number] {
                -moz-appearance: textfield;
            }
            .select-input {
                border-top: 0;
                border-left: 0;
                border-right: 0;
                border-bottom: 1px solid gray;
                box-shadow: none;
                background: white;
                text-align:center;
            }
            select::-ms-expand {
                display: none;
            }
            .input-church-field {
                border-top: 0;
                border-left: 0;
                border-right: 0;
                padding:0;
                border-bottom: 1px solid gray;
                box-shadow: none;
                background: white;
            }
            .title-year {
                font-size:3em;
                font-weight: 100;
                color: #0a0a0a;
            }
            .padding-1 {
                padding:1em;
            }

            .fi-list {
                position: absolute;
                left: 15px;
                top: 15px;
                font-size:2.5em;
                color:black;
            }
            .menu-list-item {
                border-top: 1px solid lightgrey;
                border-bottom: 1px solid lightgrey;
                padding-top: 1em;
                padding-bottom: 1em;
            }
            .menu-list-item:hover {
                background-color: WhiteSmoke;
            }
            #bottom-login {
                position: absolute;
                bottom: 10px;
                width:100%;
            }
            .float-right {
                float:right;
            }
            .one-em {
                font-size: 1em;
            }
            .two-em {
                font-size: 2em;
            }
            .three-em {
                font-size: 3em;
            }

            /* size specific style section */
            @media screen and (max-width: 991px) {
                /* start of large tablet styles */

            }
            @media screen and (max-width: 767px) {
                /* start of medium tablet styles */

            }
            @media screen and (max-width: 479px) {
                /* start of phone styles */
                body {
                    background-color: white;
                }
            }

        </style>
        <?php
    }

    public function header_javascript(){
        DT_Mapbox_API::load_mapbox_header_scripts();
        $parts = $this->parts;
        $join_key = get_post_meta( $parts['post_id'], $parts['root'] . '_join_magic_key', true );
        if ( empty( $join_key ) ) {
            update_post_meta( $parts['post_id'], $parts['root'] . '_join_magic_key', dt_create_unique_key() );
            $join_key = get_post_meta( $parts['post_id'], $parts['root'] . '_join_magic_key', true );
        }
        $create_child_key = get_post_meta( $parts['post_id'], $parts['root'] . '_create_child_magic_key', true );
        if ( empty( $create_child_key ) ) {
            update_post_meta( $parts['post_id'], $parts['root'] . '_create_child_magic_key', dt_create_unique_key() );
            $create_child_key = get_post_meta( $parts['post_id'], $parts['root'] . '_create_child_magic_key', true );
        }
        $join_url = site_url() . '/' . $this->parts['root'] . '/join/' . $join_key;
        $create_child_url = site_url() . '/' . $this->parts['root'] . '/create_child/' . $create_child_key;
        ?>
        <script>
            var jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'root' => esc_url_raw( rest_url() ),
                'site_url' => esc_url_raw( trailingslashit( site_url() ) ),
                'magic_url' => $this->magic_url,
                'join_url' => $join_url,
                'create_child_url' => $create_child_url,
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'parts' => $this->parts,
                'name' => get_the_title( $this->parts['post_id'] ),
                'translations' => [
                    'add' => __( 'Add Report', 'disciple-tools-streams' ),
                    'search_location' => 'Search city or neighborhood'
                ],
            ]) ?>][0]

            jQuery(document).ready(function($){
                clearInterval(window.fiveMinuteTimer)

                /* LOAD */
                let spinner = jQuery('.loading-spinner')
                let title = jQuery('#title')
                let content = jQuery('#content')

                /* set title */
                title.html( window.lodash.escape( jsObject.name ) )

                /* post */
                window.api_post = ( action, data ) => {
                    return jQuery.ajax({
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
                            console.log(e)
                        })
                }

                /* INVITATION FUNCTIONS */

                /* REPORT FUNCTIONS */
                window.load_reports = ( data ) => {
                    content.empty()
                    jQuery.each(data, function(i,v) {
                        content.prepend(`
                                 <div class="cell">
                                     <div class="center"><span class="title-year">${window.lodash.escape( i )}</span> </div>
                                     <table class="hover"><tbody id="report-list-${window.lodash.escape( i )}"></tbody></table>
                                 </div>
                             `)
                        let list = jQuery('#report-list-'+window.lodash.escape( i ))
                        jQuery.each(v, function(ii,vv){
                            list.append(`
                                <tr><td>${window.lodash.escape( vv.value )} total ${window.lodash.escape( vv.payload.type )} in ${window.lodash.escape( vv.label )}</td><td style="vertical-align: middle;"><button type="button" class="button small alert delete-report" data-id="${window.lodash.escape( vv.id )}" style="margin: 0;float:right;">&times;</button></td></tr>
                            `)
                        })
                    })

                    jQuery('.delete-report').on('click', function(e){
                        let id = jQuery(this).data('id')
                        jQuery(this).attr('disabled', 'disabled')
                        window.delete_report( id )
                    })

                    spinner.removeClass('active')
                }

                window.get_reports = () => {
                    jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify({ action: 'get', parts: jsObject.parts }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                        }
                    })
                        .done(function(data){
                            window.load_reports( data )
                        })
                        .fail(function(e) {
                            console.log(e)
                            jQuery('#error').html(e)
                        })
                }

                window.get_geojson = ( year ) => {
                    return jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify({ action: 'geojson', parts: jsObject.parts, data: year }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                        }
                    })
                        .fail(function(e) {
                            console.log(e)
                            jQuery('#error').html(e)
                        })
                }

                window.get_statistics = () => {
                    return jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify({ action: 'statistics', parts: jsObject.parts }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                        }
                    })
                        .fail(function(e) {
                            console.log(e)
                            jQuery('#error').html(e)
                        })
                }

                window.add_new_listener = () => {
                    let d = new Date()
                    let n = d.getFullYear()
                    let e = n - 11
                    let ten_years = ''
                    for(var i = n; i>=e; i--){
                        ten_years += `<option value="${window.lodash.escape( i )}-12-31 23:59:59">${window.lodash.escape( i )}</option>`.toString()
                    }

                    jQuery('#add-report-button').on('click', function(e){
                        jQuery('#add-report-button').hide()
                        jQuery('#add-form-wrapper').empty().append(`
                            <div class="grid-x grid-x-padding" id="new-report-form">
                                <div class="cell center">At the end of&nbsp;
                                    <select id="year" class="select-input year-input">
                                        ${ten_years}
                                    </select>
                                    &nbsp;there were
                                </div>
                                <div class="cell center">
                                    <input type="number" id="baptisms_value" class="number-input add-number-input" placeholder="#" value="0" />&nbsp;baptisms,
                                    <input type="number" id="disciples_value" class="number-input add-number-input" placeholder="#" value="0" />&nbsp;disciples,
                                    <input type="number" id="churches_value" class="number-input add-number-input" placeholder="#" value="0" />&nbsp;churches in
                                </div>
                                <div class="cell">
                                    <div id="mapbox-wrapper">
                                        <div id="mapbox-autocomplete" class="mapbox-autocomplete input-group" data-autosubmit="false" data-add-address="true">
                                            <input id="mapbox-search" type="text" name="mapbox_search" class="input-group-field" autocomplete="off" placeholder="${ window.lodash.escape( jsObject.translations.search_location ) /*Search Location*/ }" />
                                            <div class="input-group-button">
                                                <button id="mapbox-spinner-button" class="button hollow" style="display:none;border-color:lightgrey;">
                                                    <span class="" style="border-radius: 50%;width: 24px;height: 24px;border: 0.25rem solid lightgrey;border-top-color: black;animation: spin 1s infinite linear;display: inline-block;"></span>
                                                </button>
                                                <button id="mapbox-clear-autocomplete" class="button alert input-height delete-button-style mapbox-delete-button" type="button" title="${ window.lodash.escape( jsObject.translations.clear ) /*Delete Location*/}" style="display:none;">&times;</button>
                                            </div>
                                            <div id="mapbox-autocomplete-list" class="mapbox-autocomplete-items"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="cell center padding-1" >
                                    <button class="button large save-report" type="button" id="save_new_report" disabled="disabled">Save</button>
                                    <button class="button large save-report" type="button" id="save_and_add_new_report" disabled="disabled">Save and Add</button>
                                    <button class="button large alert" type="button" id="cancel_new_report">&times;</button>
                                </div>
                            </div>
                        `)

                        window.write_input_widget()

                        jQuery('.number-input').focus(function(e){
                            window.currentEvent = e
                            if ( e.currentTarget.value === '0' ){
                                e.currentTarget.value = ''
                            }
                        })

                        jQuery('#save_new_report').on('click', function(){
                            window.insert_report()
                            jQuery('#add-form-wrapper').empty()
                            jQuery('#add-report-button').show()
                        })

                        jQuery('#save_and_add_new_report').on('click', function(){
                            window.insert_report()
                            jQuery('#add-form-wrapper').empty()
                            jQuery('#add-report-button').click()
                            jQuery('#value').focus()
                        })

                        jQuery('#cancel_new_report').on('click', function(){
                            window.get_reports()
                            jQuery('#add-form-wrapper').empty()
                            jQuery('#add-report-button').show()
                        })

                        jQuery('#mapbox-search').on('change', function(e){
                            if ( typeof window.selected_location_grid_meta !== 'undefined' || window.selected_location_grid_meta !== '' ) {
                                jQuery('.save-report').removeAttr('disabled')
                            }
                        })
                    })
                }

                window.insert_report = () => {
                    spinner.addClass('active')

                    let year = jQuery('#year').val()
                    let data = []
                    let baptisms_value = jQuery('#baptisms_value').val()
                    if ( 0 < baptisms_value ) {
                        data.push({ type: 'baptisms', value: baptisms_value })
                    }
                    let disciples_value = jQuery('#disciples_value').val()
                    if ( 0 < disciples_value ) {
                        data.push({ type: 'disciples', value: disciples_value })
                    }
                    let churches_value = jQuery('#churches_value').val()
                    if ( 0 < churches_value ) {
                        data.push({ type: 'churches', value: churches_value })
                    }
                   if ( 0 == baptisms_value && 0 == disciples_value && 0 == churches_value ) {
                       return
                   }

                    let report = {
                        action: 'insert',
                        parts: jsObject.parts,
                        time_end: year,
                        data: data
                    }

                    if ( typeof window.selected_location_grid_meta !== 'undefined' && ( typeof window.selected_location_grid_meta.location_grid_meta !== 'undefined' || window.selected_location_grid_meta.location_grid_meta !== '' ) ) {
                        report.location_grid_meta = window.selected_location_grid_meta.location_grid_meta
                    }
                    else if ( jQuery('#new_contact_address').val() ) {
                        report.address = jQuery('#new_contact_address').val()
                    }

                    jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify(report),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                        }
                    })
                        .done(function(data){
                            window.load_reports( data )
                        })
                        .fail(function(e) {
                            console.log(e)
                            jQuery('#error').html(e)
                        })
                }

                window.delete_report = ( id ) => {
                    spinner.addClass('active')

                    jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify({ action: 'delete', parts: jsObject.parts, report_id: id }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce )
                        }
                    })
                        .done(function(data){
                            window.load_reports( data )
                        })
                        .fail(function(e) {
                            console.log(e)
                            jQuery('#error').html(e)
                        })
                }

            })
        </script>
        <?php
    }

    public static function _wp_enqueue_scripts(){
        DT_Mapbox_API::load_mapbox_header_scripts();
        DT_Mapbox_API::load_mapbox_search_widget();
    }

    public function nav() {
        $actions = $this->magic->list_actions( $this->type );
        $has_children = $this->has_children;
        ?>
        <!-- off canvas menus -->
        <div class="off-canvas-wrapper">
            <!-- Left Canvas -->
            <div class="off-canvas position-left" id="offCanvasLeft" data-off-canvas data-transition="push">
                <button class="close-button" aria-label="Close alert" type="button" data-close>
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="grid-x grid-padding-x menu-list" style="padding:1em">
                    <div class="cell"><br><br></div>
                    <?php
                    foreach ( $actions as $action => $label ) {
                        if ( substr( $action, 0, 1 ) === '_' ) {
                            continue;
                        }
                        if ( 'children' === $action && ! $has_children ) {
                            continue;
                        }
                        ?>
                        <div class="cell menu-list-item"><a href="<?php echo esc_url( $this->magic_url . $action ) ?>"><h3><i class="<?php echo esc_attr( $this->action_icons( $action ) ) ?>"></i> <?php echo esc_html( $label ) ?></h3></a></div>
                        <?php
                    }
                    ?>
                    <br><br>
                </div>
                <div class="grid-x grid-padding-x menu-list" id="bottom-login">
                    <div class="cell menu-list-item center">
                        <a href="<?php echo esc_url( site_url() . '/settings' ) ?>">Login</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid-x">
            <div class="cell" id="top-bar">
                <button type="button" style="" id="menu-icon" data-open="offCanvasLeft"><i class="fi-list"></i></button>
                <div id="title"></div>
            </div>
        </div>
        <?php
    }

    public function action_icons( $action ) {
        $icons = [
            '' => 'fi-home',
            'manage' => 'fi-pencil',
            'stats' => 'fi-list-thumbnails',
            'maps' => 'fi-map',
            'invite' => 'fi-at-sign',
            'children' => 'fi-share'
        ];
        return $icons[$action] ?? '';
    }

    public function home_body(){
        $actions = $this->magic->list_actions( $this->type );
        $has_children = $this->has_children;

        if ( empty( $this->post ) ) {
            $this->post_id = $this->parts["post_id"];
            $this->post = DT_Posts::get_post( $this->post_type, $this->parts["post_id"], true, false );
            if ( is_wp_error( $this->post ) ){
                return;
            }
        }
        $post = $this->post;

        $this->nav();
        ?>

        <?php if ( isset( $post['title'] ) ) : ?>
            <div class="grid-x center">
                <div class="cell">
                    <a style="font-size: .8rem;" href="<?php echo esc_url( site_url() . '/' . $this->root . '/access/' ) ?>">Not <?php echo esc_html( $post['title'] ) ?>?</a>
                </div>
            </div>
            <hr>
        <?php endif; ?>

        <div id="wrapper">
            <div class="grid-x">
                <div class="cell top-message"></div>
                <?php
                foreach ( $actions as $action => $label ) {
                    if ( empty( $action ) ) {
                        continue;
                    }
                    if ( substr( $action, 0, 1 ) === '_' ) {
                        continue;
                    }
                    if ( 'children' === $action && ! $has_children ) {
                        continue;
                    }
                    ?>
                    <div class="cell">
                        <a class="button large expanded intro-profile" href="<?php echo esc_url( $this->magic_url . $action ) ?>"><span class="uppercase"><?php echo esc_html( $label ) ?></span></a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <?php
    }

    public function manage_body(){
        DT_Mapbox_API::geocoder_scripts();
        $this->nav();
        ?>

        <div id="custom-style"></div>
        <div id="wrapper">

            <div class="grid-x" id="add-new">
                <div class="cell center"><button type="button" id="add-report-button" class="button large" style="min-width:200px;">Add Report</button></div>
                <div id="add-form-wrapper"></div>
            </div>
            <hr>
            <div class="grid-x grid-padding-x" id="main-section" style="height: inherit !important;">
                <div class="cell center" id="bottom-spinner"><span class="loading-spinner active"></span></div>
                <div class="cell" id="content"><div class="center">... loading</div></div>
                <div class="cell grid" id="error"></div>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($){
                window.get_reports()
                window.add_new_listener()
            })
        </script>
        <?php
    }

    public function stats_body(){
        $this->nav();
        ?>
        <div id="custom-style"></div>
        <div id="wrapper">
            <div class="grid-x ">
                <div class="cell center" id="title"></div>
                <div class="cell center">
                    <select style="width:150px;display:none;" id="data_streams">
                        <option value="self"><span id="self_option">Self</span></option>
                        <option value="children"><span id="children_option">Children</span></option>
                        <option value="all"><span id="all_option">Self + Children</span></option>
                    </select>
                </div>
            </div>
            <hr>
            <div class="grid-x grid-padding-x">
                <div class="cell center" id="bottom-spinner"><span class="loading-spinner active"></span></div>
                <div class="cell" id="content"><div class="center">... loading</div></div>
                <div class="cell grid" id="error"></div>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($){
                clearInterval(window.fiveMinuteTimer)

                /* LOAD */
                let spinner = $('.loading-spinner')
                let title = $('#title')
                let content = $('#content')
                let view = $('#data_streams')

                /* set title */
                title.html( jsObject.translations.title )

                /* set vertical size the form column*/
                $('#custom-style').append(`<style>#wrapper { height: inherit !important; }</style>`)

                window.get_statistics().then(function(data){
                    window.data = data
                    window.write_summary( data.self )
                    spinner.removeClass('active')
                    if ( data.has_children ) {
                        view.show()
                    }
                })/* end get_statistics */

                view.on('change', function(e){
                    let data_view = view.val()
                    if ( 'self' === data_view ) {
                        window.write_summary( window.data.self )
                    } else if ( 'children' === data_view ) {
                        window.write_summary( window.data.children )
                    } else {
                        window.write_summary( window.data.compiled )
                    }
                })

                window.write_summary = ( data ) => {
                    if ( data.length < 1 ) {
                        return
                    }
                    content.empty()
                    $.each(data, function(i,v){
                        content.prepend(`
                        <div class="grid-x">
                            <div class="cell center">
                                <span class="stat-year">${i}</span><br>
                            </div>

                            <div class="cell center">
                                <span class="stat-heading">Total Baptisms</span><br>
                                <span id="total_churches" class="stat-number">${v.total_baptisms}</span>
                            </div>
                            <div class="cell center">
                                <span class="stat-heading">Total Disciples</span><br>
                                <span id="total_disciples" class="stat-number">${v.total_disciples}</span>
                            </div>
                            <div class="cell center">
                                <span class="stat-heading">Total Churches</span><br>
                                <span id="total_churches" class="stat-number">${v.total_churches}</span>
                            </div>
                            <div class="cell center">
                                <span class="stat-heading">Engaged Counties</span><br>
                                <span id="total_churches" class="stat-number">${v.total_counties}</span>
                            </div>
                            <div class="cell center">
                                <span class="stat-heading">Engaged States</span><br>
                                <span id="total_churches" class="stat-number">${v.total_states}</span>
                            </div>
                            <div class="cell center">
                                <span class="stat-heading">Engaged Countries</span><br>
                                <span id="total_churches" class="stat-number">${v.total_countries}</span>
                            </div>
                        </div>
                        <hr>
                    `)
                    })
                }
            }) /* end .ready */
        </script>
        <?php
    }

    public function maps_body(){
        DT_Mapbox_API::geocoder_scripts();
        $this->nav();
        ?>
        <div id="custom-style"></div>
        <style>
            #wrapper {
                width: 100% !important;
                max-width: 100% !important;
            }
            #content {
                width: 100% !important;
                max-width: 100% !important;
            }
        </style>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell center" id="title"></div>
                <div class="cell center">
                    <button class="button-small button button-filter" style="background-color: royalblue;" id="baptisms">Baptisms</button>
                    <button class="button-small button button-filter" style="background-color: orange;" id="disciples">Disciples</button>
                    <button class="button-small button button-filter" style="background-color: green;" id="churches">Churches</button>
                    <button class="button-small button hollow button-filter" id="all">All</button><br>
                    <select style="width:150px;" id="year_filter"></select>
                    <select style="width:150px;display:none;" id="data_streams">
                        <option value="self"><span id="self_option">Self</span></option>
                        <option value="children"><span id="children_option">Children</span></option>
                        <option value="all"><span id="all_option">Self + Children</span></option>
                    </select>
                </div>
            </div>
            <div class="grid-x grid-padding-x">
                <div class="cell center" id="bottom-spinner"><span class="loading-spinner active"></span></div>
                <div class="cell" id="content">
                    <div id="map-wrapper">
                        <div id='map'></div>
                    </div>
                </div>
                <div class="cell grid" id="error"></div>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($) {
                clearInterval(window.fiveMinuteTimer)

                let d = new Date()
                let n = d.getFullYear()
                let e = n - 11
                let ten_years = ''
                for(var i = n; i>=e; i--){
                    ten_years += `<option value="${window.lodash.escape( i )}">${window.lodash.escape( i )}</option>`.toString()
                }
                let year_filter = jQuery('#year_filter')
                year_filter.append(ten_years)

                /* LOAD */
                let spinner = $('.loading-spinner')
                let title = $('#title')
                title.html( jsObject.translations.title )

                /* set vertical size the form column*/
                $('#custom-style').append(`
                    <style>
                        #wrapper {
                            height: ${window.innerHeight}px !important;
                        }
                        #map-wrapper {
                            height: ${window.innerHeight - 150}px !important;
                        }
                        #map {
                            height: ${window.innerHeight - 150}px !important;
                        }
                    </style>`)

                window.get_geojson().then(function (data) {
                    window.data = data
                    mapboxgl.accessToken = jsObject.map_key;
                    window.map = new mapboxgl.Map({
                        container: 'map',
                        style: 'mapbox://styles/mapbox/light-v10',
                        center: [-98, 38.88],
                        minZoom: 1,
                        maxZoom: 14,
                        zoom: 3
                    });

                    map.dragRotate.disable();
                    map.touchZoomRotate.disableRotation();

                    window.layers = [
                        'layer-baptisms-circle',
                        'layer-baptisms-count',
                        'layer-disciples-circle',
                        'layer-disciples-count',
                        'layer-churches-circle',
                        'layer-churches-count',
                    ]

                    map.on('load', function () {

                        if ( data.features.length < 1 ) {
                            spinner.removeClass('active')
                            return
                        }

                        window.data_view = jQuery('#data_streams').val()
                        let data_streams = jQuery('#data_streams')
                        if ( data.has_children ) {
                            data_streams.show()
                        }

                        // build layers
                        let d = new Date();
                        let year = d.getFullYear();

                        window.build_layers( map, data, year.toString() )

                        // listen for year select
                        year_filter.on('change', function(e){
                            let layer_var = 'churches'
                            for( const layer_id of window.layers) {
                                if ( layer_id.search('churches') !== -1 ) {
                                    layer_var = 'churches'
                                } else if ( layer_id.search('disciples') !== -1 ) {
                                    layer_var = 'disciples'
                                } else {
                                    layer_var = 'baptisms'
                                }
                                if ( 'self' === window.data_view ) {
                                    map.setFilter( layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", jQuery(this).val(), ['get', 'year'] ], ["==", 'self', ['get', 'source'] ] ]);
                                } else if ( 'children' === window.data_view ) {
                                    map.setFilter( layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", jQuery(this).val(), ['get', 'year'] ], ["==", 'children', ['get', 'source'] ] ]);
                                } else {
                                    map.setFilter( layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", jQuery(this).val(), ['get', 'year'] ] ]);
                                }
                            }
                            window.set_bounds()
                        })

                        data_streams.on( 'change', function(e){
                            window.data_view = jQuery(this).val()
                            let layer_var = 'churches'
                            for( const layer_id of window.layers) {
                                if ( layer_id.search('churches') !== -1 ) {
                                    layer_var = 'churches'
                                } else if ( layer_id.search('disciples') !== -1 ) {
                                    layer_var = 'disciples'
                                } else {
                                    layer_var = 'baptisms'
                                }
                                if ( 'self' === window.data_view ) {
                                    map.setFilter( layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", year_filter.val(), ['get', 'year'] ], ["==", 'self', ['get', 'source'] ] ]);
                                } else if ( 'children' === window.data_view ) {
                                    map.setFilter( layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", year_filter.val(), ['get', 'year'] ], ["==", 'children', ['get', 'source'] ] ]);
                                } else {
                                    map.setFilter( layer_id, [ "all", ['==', layer_var, ['get', 'type'] ], ["==", year_filter.val(), ['get', 'year'] ] ]);
                                }
                            }
                            window.set_bounds()
                        })

                        window.set_bounds = () => {
                            var bounds = new mapboxgl.LngLatBounds();
                            window.data.features.forEach(function(feature) {
                                bounds.extend(feature.geometry.coordinates);
                            });
                            window.map.fitBounds(bounds, { padding: 100 });
                        }
                        window.set_bounds()

                        // listen for button filters
                        let filter_buttons = jQuery('.button-filter')
                        let baptisms_button = jQuery('#baptisms')
                        let disciples_button = jQuery('#disciples')
                        let churches_button = jQuery('#churches')
                        baptisms_button.on('click', () => {
                            window.hide_all(map)
                            window.data_view = jQuery('#data_streams').val()
                            map.setLayoutProperty('layer-baptisms-circle', 'visibility', 'visible');
                            map.setLayoutProperty('layer-baptisms-count', 'visibility', 'visible');
                            filter_buttons.css('opacity', .4)
                            baptisms_button.css('opacity', 1)
                        })
                        disciples_button.on('click', () => {
                            window.hide_all(map)
                            window.data_view = jQuery('#data_streams').val()
                            map.setLayoutProperty('layer-disciples-circle', 'visibility', 'visible');
                            map.setLayoutProperty('layer-disciples-count', 'visibility', 'visible');
                            filter_buttons.css('opacity', .4)
                            disciples_button.css('opacity', 1)
                        })
                        churches_button.on('click', () => {
                            window.hide_all(map)
                            window.data_view = jQuery('#data_streams').val()
                            map.setLayoutProperty('layer-churches-circle', 'visibility', 'visible');
                            map.setLayoutProperty('layer-churches-count', 'visibility', 'visible');
                            filter_buttons.css('opacity', .4)
                            churches_button.css('opacity', 1)
                        })
                        jQuery('#all').on('click', () => {
                            window.data_view = jQuery('#data_streams').val()
                            window.show_all( map, window.data_view )
                            filter_buttons.css('opacity', 1)
                        })
                    });
                })

                window.build_layers = ( map, data, year ) => {
                    map.addSource('layer-source-reports', {
                        type: 'geojson',
                        data: data,
                    });

                    map.addLayer({
                        id: 'layer-churches-circle',
                        type: 'circle',
                        source: 'layer-source-reports',
                        paint: {
                            'circle-color': 'green',
                            'circle-radius': {
                                stops: [[8, 24], [11, 29], [16, 35]]
                            },
                            'circle-stroke-width': 0.2,
                            'circle-stroke-color': '#fff'
                        },
                        filter: [ "all", ['==', 'churches', ['get', 'type'] ], ["==", year, ['get', 'year'] ], ["==", 'self', ['get', 'source'] ] ]
                    });
                    map.addLayer({
                        id: 'layer-churches-count',
                        type: 'symbol',
                        source: 'layer-source-reports',
                        layout: {
                            "text-field": ['get', 'value']
                        },
                        paint: {
                            "text-color": "#ffffff"
                        },
                        filter: [ "all", ['==', 'churches', ['get', 'type'] ], ["==", year, ['get', 'year'] ], ["==", 'self', ['get', 'source'] ]  ]
                    });

                    /* disciples */
                    map.addLayer({
                        id: 'layer-disciples-circle',
                        type: 'circle',
                        source: 'layer-source-reports',
                        paint: {
                            'circle-color': 'orange',
                            'circle-radius': {
                                stops: [[8, 20], [11, 25], [16, 28]]
                            },
                            'circle-stroke-width': 0.2,
                            'circle-stroke-color': '#fff'
                        },
                        filter: [ "all", ['==', 'disciples', ['get', 'type'] ], ["==", year, ['get', 'year'] ], ["==", 'self', ['get', 'source'] ]  ]
                    });
                    map.addLayer({
                        id: 'layer-disciples-count',
                        type: 'symbol',
                        source: 'layer-source-reports',
                        layout: {
                            "text-field": ['get', 'value']
                        },
                        paint: {
                            "text-color": "#ffffff"
                        },
                        filter: [ "all", ['==', 'disciples', ['get', 'type'] ], ["==", year, ['get', 'year'] ], ["==", 'self', ['get', 'source'] ]  ]
                    });

                    /* baptism */
                    map.addLayer({
                        id: 'layer-baptisms-circle',
                        type: 'circle',
                        source: 'layer-source-reports',
                        paint: {
                            'circle-color': 'royalblue',
                            'circle-radius': {
                                stops: [[8, 16], [11, 20], [16, 23]]
                            },
                            'circle-stroke-width': 0.5,
                            'circle-stroke-color': '#fff'
                        },
                        filter: [ "all", ['==', 'baptisms', ['get', 'type'] ], ["==", year, ['get', 'year'] ], ["==", 'self', ['get', 'source'] ]  ]
                    });
                    map.addLayer({
                        id: 'layer-baptisms-count',
                        type: 'symbol',
                        source: 'layer-source-reports',
                        layout: {
                            "text-field": ['get', 'value']
                        },
                        paint: {
                            "text-color": "#ffffff"
                        },
                        filter: [ "all", ['==', 'baptisms', ['get', 'type'] ], ["==", year, ['get', 'year'] ], ["==", 'self', ['get', 'source'] ]  ]
                    });

                    map.setLayoutProperty('layer-baptisms-count', 'visibility', 'none');
                    map.setLayoutProperty('layer-disciples-count', 'visibility', 'none');
                    map.setLayoutProperty('layer-churches-count', 'visibility', 'none');
                    spinner.removeClass('active')
                }

                window.hide_all = ( map ) => {
                    for( const layer_id of window.layers) {
                        map.setLayoutProperty( layer_id, 'visibility', 'none');
                    }
                }
                window.show_all = ( map ) => {
                    window.hide_all( map )
                    const layers = ['layer-baptisms-circle', 'layer-disciples-circle', 'layer-churches-circle' ]
                    for( const layer_id of layers) {
                        map.setLayoutProperty( layer_id, 'visibility', 'visible');
                    }
                }
            })

        </script>
        <?php
    }

    public function children_body(){
        $this->nav();
        ?>
        <div id="custom-style"></div>
        <div id="wrapper">
            <hr>
            <div class="grid-x ">
                <div class="cell center" id="bottom-spinner"><span class="loading-spinner active"></span></div>
                <div class="cell" id="content"><div class="center">... loading</div></div>
                <div class="cell grid" id="error"></div>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($){

                /* LOAD */
                let spinner = $('.loading-spinner')
                let title = $('#title')
                let content = $('#content')
                let view = $('#data_streams')

                /* set title */
                title.html( jsObject.translations.title )

                /* set vertical size the form column*/
                $('#custom-style').append(`<style>#wrapper { height: inherit !important; }</style>`)

                window.api_post( 'children' ).then(function(data){
                    console.log(data)
                    window.data = data
                    window.write_summary( data )
                    spinner.removeClass('active')
                })/* end get_statistics */

                window.write_summary = ( data ) => {
                    if ( data.length < 1 ) {
                        return
                    }
                    content.empty()

                    $.each(data, function(i,v){
                        let totals_message = ''
                        let last_report_message = ''
                        if ( v.stats.year !== '' ) {
                            totals_message = 'Totals for ' + v.stats.year + ' | '
                        }
                        if ( v.stats.last_report_full !== '' ){
                            last_report_message = 'Last Report: ' + v.stats.last_report_full
                        }
                        content.prepend(`
                        <div class="grid-x grid-padding-y">
                            <div class="cell">
                                <div class="grid-x">
                                    <div class="cell center">
                                        <span class="stat-heading">${v.title}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="cell">
                                <div class="grid-x">
                                    <div class="cell small-4 center">
                                        <span class="">Baptisms</span><br>
                                        <span class="two-em">${v.stats.total_baptisms}</span>
                                    </div>
                                     <div class="cell small-4 center">
                                        <span class="">Disciples</span><br>
                                        <span class="two-em">${v.stats.total_disciples}</span>
                                    </div>
                                     <div class="cell small-4 center">
                                        <span class="">Churches</span><br>
                                        <span class="two-em">${v.stats.total_churches}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="cell" style="padding-top:1em;">
                                <div class="grid-x">
                                     <div class="cell center">
                                       ${totals_message} ${last_report_message}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                    `)
                    })
                }
            }) /* end .ready */
        </script>
        <?php
    }

    public function invite_body(){
        $this->nav();
        ?>
        <div id="custom-style"></div>
        <div id="wrapper">
            <hr>
            <div class="grid-x grid-padding-x" id="send-options">
                <div class="cell">
                    <h1>Invite a Reporter to this Stream</h1>
                    <p>Invite someone to assist you in reporting movement data in this stream. This person will see and edit any reports you have created in this stream like you do.</p>
                    <div class="button-group">
                        <a class="button hollow copy_to_clipboard" id="collaborative_reporting_copy" data-value="">Copy Link</a>
                        <a class="button hollow" style="display:none;" id="collaborative_reporting_email">Send Email</a>
                        <a class="button hollow" style="display:none;" id="collaborative_reporting_sms">Send SMS</a>
                    </div>
                    <div class="input-group input-field collaborative_reporting_email" style="display:none;">
                        <span class="input-group-label">email</span>
                        <input class="input-group-field" type="email" placeholder="email address" id="collaborative_reporting_email_value">
                        <div class="input-group-button">
                            <input type="submit" class="button submit_button collaborative_reporting_email_submit" value="Send" data-for="collaborative_reporting_email_value" data-type="collaborative_reporting">
                        </div>
                    </div>
                    <div class="input-group input-field collaborative_reporting_sms" style="display:none;">
                        <span class="input-group-label">sms</span>
                        <input class="input-group-field" type="tel" placeholder="phone number" id="collaborative_reporting_sms_value">
                        <div class="input-group-button">
                            <input type="submit" class="button submit_button collaborative_reporting_text_submit" value="Send" data-for="collaborative_reporting_sms_value" data-type="collaborative_reporting">
                        </div>
                    </div>
                </div>
                <div class="cell">
                    <h1>Invite to Start New Child Stream</h1>
                    <p>Invite someone to create a new child stream to this stream. This will link their stream in the system as a child to this stream. Child stream reports total up and are visible to parent streams.</p>
                    <div class="button-group">
                        <a class="button hollow copy_to_clipboard" id="child_stream_copy" data-value="">Copy Link</a>
                        <a class="button hollow" style="display:none;" id="child_stream_email">Send Email</a>
                        <a class="button hollow" style="display:none;" id="child_stream_sms">Send SMS</a>
                    </div>
                    <div class="input-group input-field child_stream_email" style="display:none;">
                        <span class="input-group-label">email</span>
                        <input class="input-group-field" type="email" placeholder="email address" id="child_stream_email_value">
                        <div class="input-group-button">
                            <input type="submit" class="button submit_button child_stream_email_submit" value="Send" data-for="child_stream_email_value" data-type="child_stream">
                        </div>
                    </div>
                    <div class="input-group input-field child_stream_sms" style="display:none;">
                        <span class="input-group-label">sms</span>
                        <input class="input-group-field" type="tel" placeholder="phone number" id="child_stream_sms_value">
                        <div class="input-group-button">
                            <input type="submit" class="button submit_button child_stream_text_submit" value="Send" data-for="child_stream_sms_value" data-type="child_stream">
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- form wrapper -->
        <script>
            jQuery(document).ready(function($){
                jQuery('#collaborative_reporting_copy').data('value', jsObject.join_url )
                jQuery('#child_stream_copy').data('value', jsObject.create_child_url )
                jQuery('#new_stream_copy').data('value', jsObject.site_url + jsObject.parts.root + '/access/' )

                window.add_invite_listener = () => {

                    jQuery('.loading-spinner').removeClass('active')

                    jQuery('#collaborative_reporting_email').on('click', function(e){
                        jQuery('.input-field').hide()
                        jQuery('.collaborative_reporting_email').show()
                    })
                    jQuery('#collaborative_reporting_sms').on('click', function(e){
                        jQuery('.input-field').hide()
                        jQuery('.collaborative_reporting_sms').show()
                    })

                    jQuery('#child_stream_email').on('click', function(e){
                        jQuery('.input-field').hide()
                        jQuery('.child_stream_email').show()
                    })
                    jQuery('#child_stream_sms').on('click', function(e){
                        jQuery('.input-field').hide()
                        jQuery('.child_stream_sms').show()
                    })

                    jQuery('#new_stream_email').on('click', function(e){
                        jQuery('.input-field').hide()
                        jQuery('.new_stream_email').show()
                    })
                    jQuery('#new_stream_sms').on('click', function(e){
                        jQuery('.input-field').hide()
                        jQuery('.new_stream_sms').show()
                    })

                    jQuery('.submit_button').on('click', function(e){
                        let input_value_key = jQuery(this).data('for')
                        let input_type = jQuery(this).data('type')
                        let input_value = jQuery('#'+input_value_key).val()
                        console.log(input_value)
                        console.log(input_value_key)
                        console.log(input_type)
                        // window.send( input_type, input_value )
                        success_send()
                    })
                }
                window.add_invite_listener()

                function success_send( ) {
                    let send_options = jQuery('#send-options')
                    send_options.empty().html(`
                    <div class="cell">
                        <h1>Private link sent!</h1>
                    </div>
                    `)
                }
            })
        </script>
        <?php
    }


    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_api_routes() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/'.$this->type, [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->type.'/all', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint_all' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
        register_rest_route(
            $namespace, '/'.$this->type.'/all_children', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'endpoint_all_children' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function endpoint( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['parts'], $params['parts']['meta_key'], $params['parts']['public_key'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $params = dt_recursive_sanitize_array( $params );

        // validate
        $magic = $this->magic;
        $post_id = $magic->get_post_id( $params['parts']['meta_key'], $params['parts']['public_key'] );

        if ( ! $post_id ){
            return new WP_Error( __METHOD__, "Missing post record", [ 'status' => 400 ] );
        }

        $action = sanitize_text_field( wp_unslash( $params['action'] ) );

        switch ( $action ) {
            case 'insert':
                return $this->insert_report( $params, $post_id );
            case 'delete':
                return $this->delete_report( $params, $post_id );
            case 'get':
                return $this->retrieve_reports( $post_id );
            case 'geojson':
                return $this->geojson_reports( $post_id );
            case 'children':
                return $this->children_list( $post_id );
            case 'statistics':
                return [
                    'self' => $this->statistics_reports( $post_id, 'self' ),
                    'children' => $children = $this->statistics_reports( $post_id, 'children' ),
                    'compiled' => $this->statistics_reports( $post_id, 'compiled' ),
                    'has_children' => ! empty( $children )
                ];
            case 'get_all':
                return [
                    'reports' => $this->retrieve_reports( $post_id ),
                    'geojson' => $this->geojson_reports( $post_id ),
                ];
            default:
                return new WP_Error( __METHOD__, "Missing valid action", [ 'status' => 400 ] );
        }
    }

    public function endpoint_all( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['post_id'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $post_id = sanitize_text_field( wp_unslash( $params['post_id'] ) );

        if ( ! Disciple_Tools_Posts::can_view( 'streams', $post_id ) ) {
            return new WP_Error( __METHOD__, "Do not have permission", [ 'status' => 401 ] );
        }

        $data = [];
        $data['reports'] = $this->retrieve_reports( $post_id );
        $data['stats'] = $this->statistics_reports( $post_id );
        $data['geojson'] = $this->geojson_reports( $post_id );
        return $data;
    }

    public function endpoint_all_children( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( ! isset( $params['post_id'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        $post_id = sanitize_text_field( wp_unslash( $params['post_id'] ) );

        if ( ! Disciple_Tools_Posts::can_view( 'streams', $post_id ) ) {
            return new WP_Error( __METHOD__, "Do not have permission", [ 'status' => 401 ] );
        }

        $data = [];
        $data['reports'] = $this->retrieve_reports( $post_id, true );
        $data['stats'] = $this->statistics_reports( $post_id, true );
        $data['geojson'] = $this->geojson_reports( $post_id, true );
        return $data;
    }

    public function insert_report( $params, $post_id ) {

        if ( ! isset( $params['parts']['root'], $params['parts']['type'], $params['data'], $post_id ) ){
            return new WP_Error( __METHOD__, "Missing params in insert report", [ 'status' => 400 ] );
        }

        if ( ! is_array( $params['data'] ) ) {
            return new WP_Error( __METHOD__, "Subtype must be an array", [ 'status' => 400 ] );
        }

        foreach ( $params['data'] as $data ) {
            $args = [
                'parent_id' => null,
                'post_id' => $post_id,
                'post_type' => 'streams',
                'type' => $params['parts']['root'],
                'subtype' => $params['parts']['type'],
                'payload' => [
                    'type' => $data['type'] // churches or baptisms
                ],
                'value' => $data['value'],
                'time_begin' => empty( $params['time_begin'] ) ? null : strtotime( $params['time_begin'] ),
                'time_end' => empty( $params['time_end'] ) ? time() : strtotime( $params['time_end'] ),
                'timestamp' => time(),
            ];

            if ( ! empty( $params['value'] ) ){
                $args['value'] = $params['value'];
            }

            if ( isset( $params['location_grid_meta'] ) ){
                $args['lng'] = $params['location_grid_meta']['values'][0]['lng'];
                $args['lat'] = $params['location_grid_meta']['values'][0]['lat'];
                $args['level'] = $params['location_grid_meta']['values'][0]['level'];
                $args['label'] = $params['location_grid_meta']['values'][0]['label'];

                $geocoder = new Location_Grid_Geocoder();
                $grid_row = $geocoder->get_grid_id_by_lnglat( $args['lng'], $args['lat'] );
                if ( ! empty( $grid_row ) ){
                    $args['grid_id'] = $grid_row['grid_id'];
                }
            } else if ( isset( $params['address'] ) ) {
                $args['label'] = $params['address'];
            }

            $report_id = dt_report_insert( $args );

            if ( is_wp_error( $report_id ) || empty( $report_id ) ){
                return new WP_Error( __METHOD__, "Failed to create report.", [ 'status' => 400 ] );
            }
        }

        update_post_meta( $post_id, 'report_last_modified', time() );

        return $this->retrieve_reports( $post_id );

    }

    public function retrieve_reports( $post_id, $children = false ) {
        global $wpdb;
        $data = [];

        if ( $children ) {
            $children = $this->_get_children( $post_id );
            if ( ! $children ) {
                return $data;
            }
            // @phpcs:disable
            $results = $wpdb->get_results( $wpdb->prepare( "
                    SELECT r.*, p.post_title as title
                    FROM $wpdb->dt_reports r
                    LEFT JOIN $wpdb->posts p ON p.ID=r.post_id
                    WHERE r.post_id IN ($children) 
                    ORDER BY r.time_end DESC", $post_id ), ARRAY_A );
            // @phpcs:enable
        } else {
            $results = $wpdb->get_results( $wpdb->prepare( "
                    SELECT r.*, p.post_title as title
                    FROM $wpdb->dt_reports r
                    LEFT JOIN $wpdb->posts p ON p.ID=r.post_id
                    WHERE r.post_id = %s 
                    ORDER BY r.time_end DESC", $post_id ), ARRAY_A );
        }

        if ( ! empty( $results ) ) {
            foreach ( $results as $index => $result ){
                $time = $result['time_end'];
                if ( empty( $time ) ) {
                    $time = $result['time_begin'];
                }
                if ( empty( $time ) ) {
                    continue;
                }
                $year = gmdate( 'Y', $time );
                if ( ! isset( $data[$year] ) ) {
                    $data[$year] = [];
                }
                $result['payload'] = maybe_unserialize( $result['payload'] );
                $data[$year][] = $result;
            }
        }
        return $data;
    }

    public function statistics_reports( $post_id, $view = 'self' ) : array {
        global $wpdb;
        $data = [];

        if ( 'children' === $view || 'compiled' === $view ) {
            $children = $this->_get_children( $post_id );
            if ( ! $children ) {
                return $data;
            }
            // @phpcs:disable
            $results = $results_children = $wpdb->get_results( "
            SELECT r.*,  lg.level_name, lg.name, lg.admin0_grid_id, lg0.name as country, lg.admin1_grid_id, lg1.name as state, lg.admin2_grid_id, lg2.name as county
            FROM $wpdb->dt_reports as r
            LEFT JOIN $wpdb->dt_location_grid as lg
            ON r.grid_id= lg.grid_id
            LEFT JOIN $wpdb->dt_location_grid as lg0
            ON lg.admin0_grid_id=lg0.grid_id
            LEFT JOIN $wpdb->dt_location_grid as lg1
            ON lg.admin1_grid_id=lg1.grid_id
            LEFT JOIN $wpdb->dt_location_grid as lg2
            ON lg.admin2_grid_id=lg2.grid_id
            WHERE post_id IN ($children)
            ORDER BY time_end DESC
            ", ARRAY_A );
            // @phpcs:enable
        }

        if ( 'self' === $view || 'compiled' === $view ) {
            $results = $results_self = $wpdb->get_results( $wpdb->prepare( "
            SELECT r.*,  lg.level_name, lg.name, lg.admin0_grid_id, lg0.name as country, lg.admin1_grid_id, lg1.name as state, lg.admin2_grid_id, lg2.name as county
            FROM $wpdb->dt_reports as r
            LEFT JOIN $wpdb->dt_location_grid as lg
            ON r.grid_id= lg.grid_id
            LEFT JOIN $wpdb->dt_location_grid as lg0
            ON lg.admin0_grid_id=lg0.grid_id
            LEFT JOIN $wpdb->dt_location_grid as lg1
            ON lg.admin1_grid_id=lg1.grid_id
            LEFT JOIN $wpdb->dt_location_grid as lg2
            ON lg.admin2_grid_id=lg2.grid_id
            WHERE post_id = %s
            ORDER BY time_end DESC
            ", $post_id ), ARRAY_A );
        }

        if ( 'compiled' === $view ) {
            $results = array_merge( $results_self, $results_children );
        }

        $stats_template = [
            'total_baptisms' => 0,
            'total_disciples' => 0,
            'total_churches' => 0,
            'total_countries' => 0,
            'total_states' => 0,
            'total_counties' => 0,
            'countries' => [],
            'states' => [],
            'counties' => [],
            'last_report' => 0,
            'last_report_full' => '',
            'year' => ''
        ];

        if ( empty( $results ) ){
            $year = gmdate( 'Y', strtotime( 'Today' ) );
            $data[$year] = $stats_template;
            return $data;
        }

        $countries = [];
        $states = [];
        $counties = [];

        foreach ( $results as $result ){

            /*time*/
            $time = $result['time_end'];
            if ( empty( $time ) ) {
                $time = $result['time_begin'];
            }
            if ( empty( $time ) ) {
                continue;
            }
            $year = gmdate( 'Y', $time );
            if ( ! isset( $data[$year] ) ) {
                $data[$year] = $stats_template;
            }
            $result['payload'] = maybe_unserialize( $result['payload'] );

            if ( empty( $result['grid_id'] ) ) {
                continue;
            }

            // set levels
            if ( ! isset( $countries[$year] ) ) {
                $countries[$year] = [];
            }
            if ( ! isset( $states[$year] ) ) {
                $states[$year] = [];
            }
            if ( ! isset( $counties[$year] ) ) {
                $counties[$year] = [];
            }
            if ( ! isset( $countries[$year][$result['admin0_grid_id'] ] ) ) {
                $countries[$year][ $result['admin0_grid_id'] ] = [
                    'churches' => 0,
                    'disciples' => 0,
                    'baptisms' => 0,
                    'name' => $result['country']
                ];
            }
            if ( ! isset( $states[$year][$result['admin1_grid_id'] ] ) ) {
                $states[$year][$result['admin1_grid_id'] ] = [
                    'churches' => 0,
                    'disciples' => 0,
                    'baptisms' => 0,
                    'name' => $result['state'] . ', ' . $result['country']
                ];
            }
            if ( ! isset( $counties[$year][$result['admin2_grid_id'] ] ) ) {
                $counties[$year][$result['admin2_grid_id'] ] = [
                    'churches' => 0,
                    'disciples' => 0,
                    'baptisms' => 0,
                    'name' => $result['county'] . ', ' . $result['state'] . ', ' . $result['country']
                ];
            }

            // add churches and baptisms
            if ( isset( $result['payload']['type'] ) && $result['payload']['type'] === 'churches' ) {
                $data[$year]['total_churches'] = $data[$year]['total_churches'] + intval( $result['value'] ); // total
                $countries[$year][$result['admin0_grid_id']]['churches'] = $countries[$year][$result['admin0_grid_id']]['churches'] + intval( $result['value'] ); // country
                $states[$year][$result['admin1_grid_id']]['churches'] = $states[$year][$result['admin1_grid_id']]['churches'] + intval( $result['value'] ); // state
                $counties[$year][$result['admin2_grid_id']]['churches'] = $counties[$year][$result['admin2_grid_id']]['churches'] + intval( $result['value'] ); // counties
            }
            else if ( isset( $result['payload']['type'] ) && $result['payload']['type'] === 'baptisms' ) {
                $data[$year]['total_baptisms'] = $data[$year]['total_baptisms'] + intval( $result['value'] );
                $countries[$year][$result['admin0_grid_id']]['baptisms'] = $countries[$year][$result['admin0_grid_id']]['baptisms'] + intval( $result['value'] );
                $states[$year][$result['admin1_grid_id']]['baptisms'] = $states[$year][$result['admin1_grid_id']]['baptisms'] + intval( $result['value'] );
                $counties[$year][$result['admin2_grid_id']]['baptisms'] = $counties[$year][$result['admin2_grid_id']]['baptisms'] + intval( $result['value'] );
            } else if ( isset( $result['payload']['type'] ) && $result['payload']['type'] === 'disciples' ) {
                $data[$year]['total_disciples'] = $data[$year]['total_disciples'] + intval( $result['value'] );
                $countries[$year][$result['admin0_grid_id']]['disciples'] = $countries[$year][$result['admin0_grid_id']]['disciples'] + intval( $result['value'] );
                $states[$year][$result['admin1_grid_id']]['disciples'] = $states[$year][$result['admin1_grid_id']]['disciples'] + intval( $result['value'] );
                $counties[$year][$result['admin2_grid_id']]['disciples'] = $counties[$year][$result['admin2_grid_id']]['disciples'] + intval( $result['value'] );
            }

            $data[$year]['total_countries'] = count( $countries[$year] );
            $data[$year]['total_states'] = count( $states[$year] );
            $data[$year]['total_counties'] = count( $counties[$year] );

            $data[$year]['countries'] = $countries[$year];
            $data[$year]['states'] = $states[$year];
            $data[$year]['counties'] = $counties[$year];

            $data[$year]['year'] = $year;

            if ( $data[$year]['last_report'] < $result['timestamp'] ) {
                $data[$year]['last_report'] = $result['timestamp'];
                $data[$year]['last_report_full'] = gmdate( 'M d, Y', $result['timestamp'] );
            }
        }

        return $data;
    }

    public function _get_children( $post_id ) {
        global $wpdb;
        $list = $wpdb->get_results("SELECT p2p_to as parent_id, p2p_from as child_id
                                            FROM $wpdb->p2p
                                            WHERE p2p_type = 'streams_to_streams';", ARRAY_A );
        if ( ! empty( $list ) && ! is_wp_error( $list ) ) {
            $children = $this->_build_children_list( $post_id, $list );
            return implode( ',', $children );
        } else {
            return false;
        }
    }

    public function _has_children( $post_id ) : bool {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*)
                                            FROM $wpdb->p2p
                                            WHERE p2p_type = 'streams_to_streams' 
                                              AND p2p_to = %s;", $post_id) );
        return ! empty( $count );
    }

    public function _build_children_list( $parent_id, $list, $children = [] ) {
        foreach ( $list as $node ) {
            if ( (string) $parent_id === (string) $node['parent_id'] ){
                $children[$node['child_id']] = $node['child_id'];
                foreach ( $list as $sub_node ) {
                    if ( $node['child_id'] === $sub_node['parent_id'] ){
                        $children = array_merge( $children, $this->_build_children_list( $node['child_id'], $list ) );
                    }
                }
            }
        }
        $data = [];
        foreach ($children as $child ){
            $data[$child] = $child;
        }
        return $data;
    }

    public function geojson_reports( $post_id ) {
        global $wpdb;
        $has_children = false;

        // @phpcs:disable
        $children = $this->_get_children( $post_id );
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT *, 'self' as source  FROM $wpdb->dt_reports WHERE post_id = %s ORDER BY time_end DESC", $post_id ), ARRAY_A );
        if ( $children ) {
            $child_results = $wpdb->get_results( $wpdb->prepare( "SELECT *, 'children' as source FROM $wpdb->dt_reports WHERE post_id IN ($children) ORDER BY time_end DESC", $post_id ), ARRAY_A ); // @phpcs:ignore
            $results = array_merge( $results, $child_results );
            $has_children = true;
        }
        // @phpcs:enable

        if ( empty( $results ) ) {
            return $this->_empty_geojson();
        }

        foreach ($results as $index => $result) {
            $results[$index]['payload'] = maybe_unserialize( $result['payload'] );
        }

        $features = [];
        foreach ($results as $result) {
            // get year
            $time = $result['time_end'];
            if ( empty( $time ) ) {
                $time = $result['time_begin'];
            }
            if ( empty( $time ) ) {
                continue;
            }
            $year = gmdate( 'Y', $time );

            // build feature
            $features[] = array(
                'type' => 'Feature',
                'properties' => array(
                    'value' => $result['value'],
                    'type' => $result['payload']['type'] ?? '',
                    'year' => $year,
                    'source' => $result['source'],
                    'label' => $result['label']
                ),
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        $result['lng'],
                        $result['lat'],
                        1
                    ),
                ),
            );
        }

        $geojson = array(
            'type' => 'FeatureCollection',
            'has_children' => $has_children,
            'features' => $features,
        );

        return $geojson;
    }

    private function _empty_geojson() {
        return array(
            'type' => 'FeatureCollection',
            'features' => array()
        );
    }

    public function children_list( $post_id ) {
        global $wpdb;
        $data = [];

        $list = $wpdb->get_results("SELECT p2p_to as parent_id, p2p_from as child_id
                                    FROM $wpdb->p2p
                                    WHERE p2p_type = 'streams_to_streams';", ARRAY_A );
        if ( empty( $list ) || is_wp_error( $list ) ) {
            return $data;
        }

        $children = $this->_build_children_list( $post_id, $list );

        foreach ( $children as $child ) {
            $stats_report = $this->statistics_reports( $child );
            $top_year = 0;
            $stats = [];
            $last_report = 0;
            foreach ( $stats_report as $year => $sr ) {
                if ( $year < $top_year ) {
                    continue;
                }
                $top_year = $year;
                $stats = $sr;
                $last_report = $sr['last_report'];
            }
            $data[$child] = [
                'title' => get_the_title( $child ),
                'stats' => $stats,
            ];
        }

        return $data;
    }

    public function delete_report( $params, $post_id ) {
        $result = Disciple_Tools_Reports::delete( $params['report_id'] );
        if ( ! $result ) {
            return new WP_Error( __METHOD__, "Failed to delete report", [ 'status' => 400 ] );
        }

        update_post_meta( $post_id, 'report_last_modified', time() );

        return $this->retrieve_reports( $post_id );
    }
}
DT_Stream_Reports::instance();