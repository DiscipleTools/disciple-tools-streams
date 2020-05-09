<?php

class DT_Streams_Post_Type {
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()
    public $trainings = false;

    public function __construct() {
        if ( class_exists( 'DT_Training' ) ) {
            $this->trainings = true;
        }

        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_action( 'p2p_init', [ $this, 'p2p_init' ] );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 10, 2 );
        add_action( 'dt_modal_help_text', [ $this, 'modal_help_text'], 10 );

        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_details_additional_section_ids', [ $this, 'dt_details_additional_section_ids' ], 10, 2 );
        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
        add_action( "post_connection_added", [ $this, "post_connection_added" ], 10, 4 );
        add_filter( "dt_user_list_filters", [ $this, "dt_user_list_filters" ], 10, 2 );
        add_filter( "dt_get_post_fields_filter", [ $this, "dt_get_post_fields_filter" ], 10, 2 );


    }

    public function after_setup_theme(){
        if ( class_exists( 'Disciple_Tools_Post_Type_Template' )) {
            new Disciple_Tools_Post_Type_Template( "streams", 'Stream', 'Streams' );
        }
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === 'streams' ){
            $fields['leader_count'] = [
                'name' => "Leaders",
                'type' => 'number',
                'default' => '0',
                'show_in_table' => true
            ];
            $fields['disciple_count'] = [
                'name' => "Disciples",
                'type' => 'number',
                'default' => '0',
                'show_in_table' => true
            ];
            $fields['group_count'] = [
                'name' => "Groups",
                'type' => 'number',
                'default' => '0',
                'show_in_table' => false
            ];
            $fields['church_count'] = [
                'name' => "Churches",
                'type' => 'number',
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
                'name' => "Phase",
                'type' => 'key_select',
                'default' => [
                    'model'   => [
                        "label" => _x( 'Model', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This stream is in the model phase.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#F43636",
                    ],
                    'assist'   => [
                        "label" => _x( 'Assist', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This stream is in the assist phase.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#F43636",
                    ],
                    'watch' => [
                        "label" => _x( 'Watch', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This stream is in the watch phase.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#FF9800",
                    ],
                    'leave' => [
                        "label" => _x( 'Leave', 'Streams Status label', 'disciple_tools' ),
                        "description" => _x( "This stream is in the leave stage. It is self-sustaining.", "Streams Status field description", 'disciple_tools' ),
                        "color" => "#FF9800",
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
            $fields['parents'] = [
                'name' => "Parent Streams",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_streams",
            ];
            $fields['children'] = [
                'name' => "Child Streams",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_streams",
            ];
            $fields['leaders'] = [
                'name' => "Leaders",
                'type' => 'connection',
                "post_type" => 'contacts',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_leaders",
            ];
            $fields['contacts'] = [
                'name' => "Key Disciples",
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
            $fields["people_groups"] = [
                "name" => __( 'People Groups', 'disciple_tools' ),
                'description' => _x( 'The people groups represented by this group.', 'Optional Documentation', 'disciple_tools' ),
                "type" => "connection",
                "post_type" => "peoplegroups",
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_peoplegroups"
            ];
            $fields["coaches"] = [
                "name" => __( 'Coach / Church Planter', 'disciple_tools' ),
                'description' => _x( 'The person who planted and/or is coaching this stream. Multiple people can be coaches / church planters of this group.', 'Optional Documentation', 'disciple_tools' ),
                "type" => "connection",
                "post_type" => "contacts",
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_coaches"
            ];
            if ( $this->trainings ) {
                $fields['training_count'] = [
                    'name' => "Trainings",
                    'type' => 'number',
                    'default' => '0',
                    'show_in_table' => false
                ];
                $fields['trainings'] = [
                    'name' => "Trainings",
                    'type' => 'connection',
                    "post_type" => 'trainings',
                    "p2p_direction" => "from",
                    "p2p_key" => "streams_to_groups",
                ];
            }
        }
        if ( $post_type === 'groups' ){
            $fields['streams'] = [
                'name' => "Streams",
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
            $fields['streams_contact'] = [
                'name' => "Key Disciple",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_contacts",
            ];
        }
        if ( $post_type === 'trainings' ){
            $fields['streams'] = [
                'name' => "Streams",
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_trainings",
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
        p2p_register_connection_type([
            'name' => 'streams_to_streams',
            'from' => 'streams',
            'to' => 'streams'
        ]);
        p2p_register_connection_type([
            'name' => 'streams_to_peoplegroups',
            'from' => 'streams',
            'to' => 'peoplegroups'
        ]);
        p2p_register_connection_type([
            'name' => 'streams_to_coaches',
            'from' => 'streams',
            'to' => 'contacts'
        ]);
        if ( $this->trainings ) {
            p2p_register_connection_type([
                'name' => 'streams_to_trainings',
                'from' => 'streams',
                'to' => 'trainings'
            ]);
        }

    }

    public function dt_details_additional_section_ids( $sections, $post_type = "" ){
        if ( $post_type === "streams"){
            $sections[] = 'connections';
            $sections[] = 'totals';
        }
        if ( $post_type === 'contacts' || $post_type === 'groups' ){
            $sections[] = 'streams';
        }
        if ( $post_type === 'trainings' ){
            $sections[] = 'streams';
        }
        return $sections;
    }

    public function dt_details_additional_section( $section, $post_type ){

        if ( $section === "details" && $post_type === "streams" ){
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>
            <div class="grid-x grid-padding-x">
                <div class="cell medium-6">
                    <?php render_field_for_display( 'status', $post_settings["fields"], $dt_post ); ?>
                </div>
                <div class="cell medium-6">
                    <?php render_field_for_display( 'coaches', $post_settings["fields"], $dt_post ); ?>
                </div>
                <div class="cell medium-6">
                    <?php /* If Mapbox Upgrade */ if ( DT_Mapbox_API::get_key() ) : ?>
                         <a class="button clear" id="new-mapbox-search"><?php esc_html_e( "add", 'zume' ) ?></a>

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
                </div>
                <div class="cell medium-6">

                    <?php render_field_for_display( 'start_date', $post_settings["fields"], $dt_post ); ?>

                </div>

            </div>
            <?php
        }

        // Connections tile on Streams details page
        if ($section === "connections" && $post_type === "streams"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <h3 class="section-header">
                <?php esc_html_e( 'Connections', 'disciple_tools' )?>
                <button class="help-button float-right" data-section="connections-help-text">
                    <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_down">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_down.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_up">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_up.svg' ) ?>"/>
                </button>
            </h3>
            <div class="section-body">
                <?php render_field_for_display( 'leaders', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'contacts', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'groups', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'people_groups', $post_settings["fields"], $dt_post ) ?>

                <?php if ( $this->trainings ) : ?>

                    <?php render_field_for_display( 'trainings', $post_settings["fields"], $dt_post ) ?>

                <?php endif; ?>

                <?php render_field_for_display( 'parents', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'children', $post_settings["fields"], $dt_post ) ?>
            </div>
        <?php }

        // Connections tile on Streams details page
        if ($section === "totals" && $post_type === "streams"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Stream Totals', 'disciple_tools' )?>
                <button class="help-button float-right" data-section="reports-help-text">
                    <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_down">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_down.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_up">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_up.svg' ) ?>"/>
                </button>
            </label>
            <div class="section-body">

                <?php render_field_for_display( 'leader_count', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'disciple_count', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'group_count', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'church_count', $post_settings["fields"], $dt_post ) ?>

                <?php if ( $this->trainings ) : ?>

                    <?php render_field_for_display( 'training_count', $post_settings["fields"], $dt_post ) ?>

                <?php endif; ?>

            </div>

        <?php }


        // Streams tile on contacts details page
        if ($section == "streams" && $post_type === "contacts"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Streams', 'disciple_tools' )?>
                <button class="help-button float-right" data-section="streams-help-text">
                    <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_down">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_down.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_up">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_up.svg' ) ?>"/>
                </button>
            </label>
            <div class="section-body">
                <?php render_field_for_display( 'streams_leader', $post_settings["fields"], $dt_post ) ?>

                <?php render_field_for_display( 'streams_participant', $post_settings["fields"], $dt_post ) ?>
            </div>


        <?php }

        // Streams tile on groups details page
        if ($section == "streams" && $post_type === "groups"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Streams', 'disciple_tools' )?>
                <button class="help-button float-right" data-section="streams-help-text">
                    <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_down">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_down.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_up">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_up.svg' ) ?>"/>
                </button>
            </label>
            <div class="section-body">

                <?php render_field_for_display( 'streams', $post_settings["fields"], $dt_post ) ?>

            </div>

        <?php }

        if ($section == "streams" && $post_type === "trainings"){
            $post_type = get_post_type();
            $post_settings = apply_filters( "dt_get_post_type_settings", [], $post_type );
            $dt_post = DT_Posts::get_post( $post_type, get_the_ID() );
            ?>

            <label class="section-header">
                <?php esc_html_e( 'Streams', 'disciple_tools' )?>
                <button class="help-button float-right" data-section="streams-help-text">
                    <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_down">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_down.svg' ) ?>"/>
                </button>
                <button class="section-chevron chevron_up">
                    <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/chevron_up.svg' ) ?>"/>
                </button>
            </label>
            <div class="section-body">

                <?php render_field_for_display( 'streams', $post_settings["fields"], $dt_post ) ?>

            </div>

        <?php }
    }

    public function modal_help_text() {
        if ( is_singular( "streams" ) ) {
            ?>
            <div class="help-section" id="connections-help-text" style="display: none">
                <h3><?php echo esc_html_x( "Connections", 'Optional Documentation', 'disciple_tools' ) ?></h3>
                <p><?php echo esc_html_x( "These are key or tracked connections to leaders, disciples, groups/churches, and trainings.", 'Optional Documentation', 'disciple_tools' ) ?></p>
                <p><?php echo esc_html_x( "Tracked connections allow for specific responsibility and tracking, versus totals tracking of stream generations.", 'Optional Documentation', 'disciple_tools' ) ?></p>
            </div>
            <div class="help-section" id="reports-help-text" style="display: none">
                <h3><?php echo esc_html_x( "Reports", 'Optional Documentation', 'disciple_tools' ) ?></h3>
                <p><?php echo esc_html_x( "These are key or tracked connections to leaders, disciples, groups/churches, and trainings.", 'Optional Documentation', 'disciple_tools' ) ?></p>
                <p><?php echo esc_html_x( "Tracked connections allow for specific responsibility and tracking, versus totals tracking of stream generations.", 'Optional Documentation', 'disciple_tools' ) ?></p>
            </div>
            <?php
        }
        if ( is_singular( "contacts" ) ) {
            ?>
            <div class="help-section" id="streams-help-text" style="display: none">
                <h3><?php echo esc_html_x( "Streams", 'Optional Documentation', 'disciple_tools' ) ?></h3>
                <p><?php echo esc_html_x( "You can connect this contact as a leader or a participant to a stream.", 'Optional Documentation', 'disciple_tools' ) ?></p>
            </div>
            <?php
        }
        if ( is_singular( "groups" ) ) {
            ?>
            <div class="help-section" id="streams-help-text" style="display: none">
                <h3><?php echo esc_html_x( "Streams", 'Optional Documentation', 'disciple_tools' ) ?></h3>
                <p><?php echo esc_html_x( "You can connect this group to a stream.", 'Optional Documentation', 'disciple_tools' ) ?></p>
            </div>
            <?php
        }
        if ( is_singular( "trainings" ) ) {
            ?>
            <div class="help-section" id="streams-help-text" style="display: none">
                <h3><?php echo esc_html_x( "Streams", 'Optional Documentation', 'disciple_tools' ) ?></h3>
                <p><?php echo esc_html_x( "You can connect this group to a stream.", 'Optional Documentation', 'disciple_tools' ) ?></p>
            </div>
            <?php
        }
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
                'name' => _x( "Model", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "model" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_proposed',
                'tab' => 'all_streams',
                'name' => _x( "Assist", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "assist" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_scheduled',
                'tab' => 'all_streams',
                'name' => _x( "Watch", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "watch" ] ],
            ];
            $filters["filters"][] = [
                'ID' => 'all_in_progress',
                'tab' => 'all_streams',
                'name' => _x( "Leave", 'List Filters', 'disciple_tools' ),
                'query' => [ "status" => [ "leave" ] ],
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