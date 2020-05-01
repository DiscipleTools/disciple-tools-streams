<?php

class DT_Streams_Post_Type {
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_action( 'p2p_init', [ $this, 'p2p_init' ] );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 10, 2 );

        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_details_additional_section_ids', [ $this, 'dt_details_additional_section_ids' ], 10, 2 );
        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
        add_action( "post_connection_added", [ $this, "post_connection_added" ], 10, 4 );
        add_filter( "dt_user_list_filters", [ $this, "dt_user_list_filters" ], 10, 2 );
        add_filter( "dt_get_post_fields_filter", [ $this, "dt_get_post_fields_filter" ], 10, 2 );
    }

    public function after_setup_theme(){
        if ( class_exists( 'Disciple_Tools_Post_Type_Template' )) {
            new Disciple_Tools_Post_Type_Template( "streams", 'Streams', 'Streamss' );
        }
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === 'streams' ){
            $fields['leader_count'] = [
                'name' => "Leaders #",
                'type' => 'text',
                'default' => '0',
                'show_in_table' => true
            ];
            $fields['contact_count'] = [
                'name' => "Participants #",
                'type' => 'text',
                'default' => '0',
                'show_in_table' => true
            ];
            $fields['group_count'] = [
                'name' => "Groups #",
                'type' => 'text',
                'default' => '0',
                'show_in_table' => false
            ];
            $fields["location_grid"] = [
                'name' => "Locations",
                'type' => 'location',
                'default' => [],
                'show_in_table' => true
            ];
            $fields["location_grid_meta"] = [
                'name' => "Locations",
                'type' => 'location_meta',
                'default' => [],
                'show_in_table' => false,
                'silent' => true,
            ];
            $fields["status"] = [
                'name' => "Status",
                'type' => 'key_select',
                'default' => [
                    'new'   => [
                        "label" => _x( 'New', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "New streams added to the system", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#F43636",
                    ],
                    'proposed'   => [
                        "label" => _x( 'Proposed', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This streams has been proposed and is in initial conversations", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#F43636",
                    ],
                    'scheduled' => [
                        "label" => _x( 'Scheduled', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This streams is confirmed, on the calendar.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#FF9800",
                    ],
                    'in_progress' => [
                        "label" => _x( 'In Progress', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This streams is confirmed, on the calendar, or currently active.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#FF9800",
                    ],
                    'complete'     => [
                        "label" => _x( "Complete", 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This streams has successfully completed", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#FF9800",
                    ],
                    'paused'       => [
                        "label" => _x( 'Paused', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This contact is currently on hold. It has potential of getting scheduled in the future.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#FF9800",
                    ],
                    'closed'       => [
                        "label" => _x( 'Closed', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This streams is no longer going to happen.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#F43636",
                    ],
                ],
                'show_in_table' => true
            ];
            $fields["start_date"] = [
                'name' => "Start Date",
                'type' => 'date',
                'default' => '',
                'show_in_table' => true
            ];
            $fields['leaders'] = [
                'name' => "Leaders",
                'type' => 'connection',
                "post_type" => 'contacts',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_leaders",
            ];
            $fields['contacts'] = [
                'name' => "Participants",
                'type' => 'connection',
                "post_type" => 'contacts',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_contacts",
            ];
            $fields['groups'] = [
                'name' => "Groups",
                'type' => 'connection',
                "post_type" => 'groups',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_groups",
            ];

        }
        if ( $post_type === 'groups' ){
            $fields['streams'] = [
                'name' => "Streamss",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_groups",
            ];
        }
        if ( $post_type === 'contacts' ){
            $fields['streams_leader'] = [
                'name' => "Leader",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_leaders",
            ];
            $fields['streams_participant'] = [
                'name' => "Participant",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_contacts",
            ];
        }
        return $fields;
    }

    public function p2p_init(){
        p2p_register_connection_type([
            'name' => 'streams_to_contacts',
            'from' => 'streams',
            'to' => 'contacts'
        ]);
        p2p_register_connection_type([
            'name' => 'streams_to_groups',
            'from' => 'streams',
            'to' => 'groups'
        ]);
        p2p_register_connection_type([
            'name' => 'streams_to_leaders',
            'from' => 'streams',
            'to' => 'contacts'
        ]);

    }

    public function dt_details_additional_section_ids( $sections, $post_type = "" ){
        if ( $post_type === "streams"){
            $sections[] = 'connections';
            $sections[] = 'location';
//            $sections[] = 'meta';
        }
        if ( $post_type === 'contacts' || $post_type === 'groups' ){
            $sections[] = 'streams';
        }
        return $sections;
    }

    public function dt_details_additional_section( $section, $post_type ){
        // top tile on streams details page // @todo remove unncessary header or add editing capability
        if ( $section === "details" && $post_type === "streams" ){
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>
            <div class="grid-x grid-padding-x">
                <div class="cell medium-6">
                    <?php render_field_for_display( 'status', $post_settings["fields"], $dt_post ); ?>
                </div>
                <div class="cell medium-6">
                    <?php render_field_for_display( 'start_date', $post_settings["fields"], $dt_post ); ?>
                </div>
            </div>
            <?php
        }

        if ($section === "location" && $post_type === "streams"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );

            ?>

            <label class="section-header">
                <?php esc_html_e( 'Location', 'disciple_tools' )?> <a class="button clear" id="new-mapbox-search"><?php esc_html_e( "add", 'zume' ) ?></a>
            </label>

            <?php /* If Mapbox Upgrade */ if ( DT_Mapbox_API::get_key() ) : ?>

                <div id="mapbox-wrapper"></div>

                <?php if ( isset( $dt_post['location_grid_meta'] ) ) : ?>

                    <!-- reveal -->
                    <div class="reveal" id="map-reveal" data-reveal>
                        <div id="map-reveal-content"><!-- load content here --><div class="loader">Loading...</div></div>
                        <button class="close-button" data-close aria-label="Close modal" type="button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

            <?php /* No Mapbox Upgrade */ else : ?>

                <?php render_field_for_display( 'location_grid', $post_settings["fields"], $dt_post ); ?>

            <?php endif; ?>


        <?php }

        // Connections tile on Streamss details page
        if ($section === "connections" && $post_type === "streams"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Connections', 'disciple_tools' )?>
            </label>

            <?php render_field_for_display( 'leaders', $post_settings["fields"], $dt_post ) ?>

            <?php render_field_for_display( 'leader_count', $post_settings["fields"], $dt_post ) ?>

            <?php render_field_for_display( 'contacts', $post_settings["fields"], $dt_post ) ?>

            <?php render_field_for_display( 'contact_count', $post_settings["fields"], $dt_post ) ?>

            <?php render_field_for_display( 'groups', $post_settings["fields"], $dt_post ) ?>

        <?php }

        // Connections tile on Streamss details page
        /*
        if ($section === "meta" && $post_type === "streams"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Details', 'disciple_tools' )?>
            </label>


            <!-- @todo make live date adding -->
            <div class="section-subheader">More dates</div>
            <div id="streams-dates"></div>

            <a class="button small primary-button" onclick="add_new_date()">add</a>

            <script>
               function add_new_date() {
                   let masonGrid = $('.grid')

                   jQuery('#streams-dates').append(`<input type='text' class='date-picker dt_date_picker hasDatepicker' id='start_date' autocomplete='off' value='February 16, 2020'>`)

                   masonGrid.masonry({
                       itemSelector: '.grid-item',
                       percentPosition: true
                   });
               }
            </script>
        <?php }
        */


        // Streamss tile on contacts details page
        if ($section == "streams" && $post_type === "contacts"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Streamss', 'disciple_tools' )?>
            </label>

            <?php render_field_for_display( 'streams_leader', $post_settings["fields"], $dt_post ) ?>

            <?php render_field_for_display( 'streams_participant', $post_settings["fields"], $dt_post ) ?>

        <?php }

        // Streamss tile on groups details page
        if ($section == "streams" && $post_type === "groups"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Streamss', 'disciple_tools' )?>
            </label>

            <?php render_field_for_display( 'streams', $post_settings["fields"], $dt_post ) ?>

        <?php }


    }

    private function update_event_counts( $streams_id, $action = "added", $type = 'contacts' ){
        $streams = get_post( $streams_id );
        if ( $type === 'contacts' ){
            $args = [
                'connected_type'   => "streams_to_contacts",
                'connected_direction' => 'from',
                'connected_items'  => $streams,
                'nopaging'         => true,
                'suppress_filters' => false,
            ];
            $contacts = get_posts( $args );
            $contact_count = get_post_meta( $streams_id, 'contact_count', true );
            if ( sizeof( $contacts ) > intval( $contact_count ) ){
                update_post_meta( $streams_id, 'contact_count', sizeof( $contacts ) );
            } elseif ( $action === "removed" ){
                update_post_meta( $streams_id, 'contact_count', intval( $contact_count ) - 1 );
            }
        }
        if ( $type === 'groups' ){
            $args = [
                'connected_type'   => "streams_to_groups",
                'connected_direction' => 'from',
                'connected_items'  => $streams,
                'nopaging'         => true,
                'suppress_filters' => false,
            ];
            $groups = get_posts( $args );
            $group_count = get_post_meta( $streams_id, 'group_count', true );
            if ( sizeof( $groups ) > intval( $group_count ) ){
                update_post_meta( $streams_id, 'group_count', sizeof( $groups ) );
            } elseif ( $action === "removed" ){
                update_post_meta( $streams_id, 'group_count', intval( $group_count ) - 1 );
            }
        }
        if ( $type === 'leaders' ){
            $args = [
                'connected_type'   => "streams_to_leaders",
                'connected_direction' => 'from',
                'connected_items'  => $streams,
                'nopaging'         => true,
                'suppress_filters' => false,
            ];
            $contacts = get_posts( $args );
            $contact_count = get_post_meta( $streams_id, 'leader_count', true );
            if ( sizeof( $contacts ) > intval( $contact_count ) ){
                update_post_meta( $streams_id, 'leader_count', sizeof( $contacts ) );
            } elseif ( $action === "removed" ){
                update_post_meta( $streams_id, 'leader_count', intval( $contact_count ) - 1 );
            }
        }
    }
    public function post_connection_added( $post_type, $post_id, $post_key, $value ){
        if ( $post_type === "streams" && ( $post_key === "contacts" || $post_key === "groups" ) ){
            $this->update_event_counts( $post_id, 'added', $post_key );
        } elseif ( ( $post_type === "contacts" || $post_type === "groups" ) && $post_key === "streams" ) {
            $this->update_event_counts( $value, 'added', $post_type );
        }
    }
    public function post_connection_removed( $post_type, $post_id, $post_key, $value ){
        if ( $post_type === "streams" && ( $post_key === "contacts" || $post_key === "groups" ) ){
            $this->update_event_counts( $post_id, 'removed', $post_key );
        } elseif ( ( $post_type === "contacts" || $post_type === "groups" ) && $post_key === "streams" ) {
            $this->update_event_counts( $value, 'removed', $post_type );
        }
    }

    public static function dt_user_list_filters( $filters, $post_type ) {
        if ( $post_type === 'streams' ) {
            $filters["tabs"][] = [
                "key" => "all_streams",
                "label" => _x( "All", 'List Filters', 'disciple_tools' ),
                "order" => 10
            ];
            // add assigned to me filters
            $filters["filters"][] = [
                'ID' => 'all_streams',
                'tab' => 'all_streams',
                'name' => _x( "All", 'List Filters', 'disciple_tools' ),
                'query' => [],
            ];
            $filters["filters"][] = [
                'ID' => 'all_new',
                'tab' => 'all_streams',
                'name' => _x( "New", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "new" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_proposed',
                'tab' => 'all_streams',
                'name' => _x( "Proposed", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "proposed" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_scheduled',
                'tab' => 'all_streams',
                'name' => _x( "Scheduled", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "scheduled" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_in_progress',
                'tab' => 'all_streams',
                'name' => _x( "In Progress", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "in_progress" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_complete',
                'tab' => 'all_streams',
                'name' => _x( "Complete", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "complete" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_paused',
                'tab' => 'all_streams',
                'name' => _x( "Paused", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "paused" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_closed',
                'tab' => 'all_streams',
                'name' => _x( "Closed", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "closed" ] ],
            ];
        }
        return $filters;
    }

    public function dt_get_post_fields_filter( $fields, $post_type ) {
        if ( $post_type === 'streams' ){
            $fields = apply_filters( 'dt_streams_fields_post_filter', $fields );
        }
        return $fields;
    }
}
DT_Streams_Post_Type::instance();