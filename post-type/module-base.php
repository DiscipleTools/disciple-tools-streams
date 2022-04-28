<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Stream_Base extends DT_Module_Base {
    public $post_type = "streams";
    public $module = "streams_base";
    public $trainings = false;

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }
        if ( class_exists( 'DT_Training' ) ) {
            $this->trainings = true;
        }

        //setup post type
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 );

        //setup tiles and fields
        add_action( 'p2p_init', [ $this, 'p2p_init' ] );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_get_post_type_settings', [ $this, 'dt_get_post_type_settings' ], 20, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 50, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 20, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

        // hooks
        add_action( "post_connection_removed", [ $this, "post_connection_removed" ], 10, 4 );
        add_action( "post_connection_added", [ $this, "post_connection_added" ], 10, 4 );
        add_filter( "dt_post_update_fields", [ $this, "dt_post_update_fields" ], 10, 3 );
        add_filter( "dt_post_create_fields", [ $this, "dt_post_create_fields" ], 10, 2 );
        add_action( "dt_post_created", [ $this, "dt_post_created" ], 10, 3 );
        add_action( "dt_comment_created", [ $this, "dt_comment_created" ], 10, 4 );

        //list
        add_filter( "dt_user_list_filters", [ $this, "dt_user_list_filters" ], 10, 2 );
        add_filter( "dt_filter_access_permissions", [ $this, "dt_filter_access_permissions" ], 20, 2 );
    }


    public function after_setup_theme(){
        if ( class_exists( 'Disciple_Tools_Post_Type_Template' )) {
            new Disciple_Tools_Post_Type_Template( "streams", __( 'Stream', 'disciple-tools-streams' ), __( 'Streams', 'disciple-tools-streams' ) );
        }
    }
    public function dt_set_roles_and_permissions( $expected_roles ){
        $expected_roles["streams_admin"] = [
            "label" => __( 'Streams Admin', 'disciple-tools-streams' ),
            "description" => "Has all permissions for streams",
            "permissions" => [
                'access_disciple_tools' => true,
                'view_any_' . $this->post_type => true,
                'dt_all_admin_' . $this->post_type => true,
            ]
        ];
        if ( !isset( $expected_roles["multiplier"] ) ){
            $expected_roles["multiplier"] = [
                "label" => __( 'Multiplier', 'disciple-tools-streams' ),
                "permissions" => []
            ];
        }
        if ( !isset( $expected_roles["dispatcher"] ) ){
            $expected_roles["dispatcher"] = [
                "label" => __( 'Dispatcher', 'disciple-tools-streams' ),
                "description" => "All D.T permissions",
                "permissions" => []
            ];
        }
        if ( !isset( $expected_roles["dt_admin"] ) ){
            $expected_roles["dt_admin"] = [
                "label" => __( 'Disciple.Tools Admin', 'disciple-tools-streams' ),
                "description" => "All D.T permissions",
                "permissions" => []
            ];
        }
        if ( !isset( $expected_roles["administrator"] ) ){
            $expected_roles["administrator"] = [
                "label" => __( 'Administrator', 'disciple-tools-streams' ),
                "description" => "All D.T permissions plus the ability to manage plugins.",
                "permissions" => []
            ];
        }

        foreach ( $expected_roles as $role => $role_value ){
            if ( isset( $expected_roles[$role]["permissions"]['access_contacts'] ) && $expected_roles[$role]["permissions"]['access_contacts'] ){
                $expected_roles[$role]["permissions"]['access_' . $this->post_type] = true;
                $expected_roles[$role]["permissions"]['create_' . $this->post_type] = true;
            }
            if ( in_array( $role, [ 'administrator', 'dispatcher', 'dt_admin' ] ) ) {
                $expected_roles[$role]["permissions"]['view_any_' . $this->post_type] = true;
                $expected_roles[$role]["permissions"]['dt_all_admin_' . $this->post_type] = true;
            }
        }

        return $expected_roles;
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === 'streams' ){
            // framework fields

            $fields["status"] = [
                'name' => __( "Status", 'disciple-tools-streams' ),
                'type' => 'key_select',
                "tile" => "status",
                'default' => [
                    'new'   => [
                        "label" => _x( 'New', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "New stream added to the system", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#ff9800"
                    ],
                    'model'   => [
                        "label" => _x( 'Model', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This stream is being actively coached and is still in the modelling stage.", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#ff9800"
                    ],
                    'assist' => [
                        "label" => _x( 'Assist', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This stream is being actively coached and is still in the assist stage.", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#4CAF50"
                    ],
                    'watch' => [
                        "label" => _x( 'Watch', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This stream is being actively coached and is still in the watch stage.", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#4CAF50"
                    ],
                    'leave'     => [
                        "label" => _x( "Leave/Launch", 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This stream is being actively coached and is still in the leave/launch stage.", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#4CAF50"
                    ],
                    'active'   => [
                        "label" => _x( 'Active', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This is an active stream in no specific stage.", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#ff9800"
                    ],
                    'paused'       => [
                        "label" => _x( 'Paused', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This contact is currently on hold. It has potential of getting scheduled in the future.", "Stream Status field description", 'disciple-tools-streams' ),
                        'color' => "#ff9800"
                    ],
                    'closed'       => [
                        "label" => _x( 'Closed', 'Stream Status label', 'disciple-tools-streams' ),
                        "description" => _x( "This stream is no longer going to happen.", "Stream Status field description", 'disciple-tools-streams' ),
                        "color" => "#366184",
                    ],
                ],
                "default_color" => "#366184",
                "select_cannot_be_empty" => true
            ];
            $fields['assigned_to'] = [
                'name'        => __( 'Assigned To', 'disciple-tools-streams' ),
                'description' => __( "Select the main person who is responsible for reporting on this stream.", 'disciple-tools-streams' ),
                'type'        => 'user_select',
                'default'     => '',
                'tile' => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/assigned-to.svg',
            ];
            $fields["coaches"] = [
                "name" => __( 'Coach', 'disciple-tools-streams' ),
                'description' => _x( 'The person who planted and/or is coaching this stream. Only one person can be assigned to a stream while multiple people can be coaches / church planters of this stream.', 'Optional Documentation', 'disciple-tools-streams' ),
                "type" => "connection",
                "post_type" => "contacts",
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_coaches",
                'tile' => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/coach.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-contact.svg',
            ];
            $fields["reporter"] = [
                "name" => __( 'Reporter', 'disciple-tools-streams' ),
                'description' => _x( 'The person who is the responsible reporter for this stream.', 'Optional Documentation', 'disciple-tools-streams' ),
                "type" => "connection",
                "post_type" => "contacts",
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_reporter",
                'tile' => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/coach.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-contact.svg',
            ];
            $fields['description'] = [
                'name' => __( "Description", 'disciple-tools-streams' ),
                'type' => 'textarea',
                'default' => '',
                'tile' => 'details',
//                'show_in_table' => false
            ];

            $fields["peoplegroups"] = [
                "name" => __( 'People Groups', 'disciple-tools-streams' ),
                'description' => _x( 'The people streams represented by this stream.', 'Optional Documentation', 'disciple-tools-streams' ),
                "type" => "connection",
                'tile' => 'details',
                "post_type" => "peoplegroups",
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_peoplegroups",
                'icon' => get_template_directory_uri() . "/dt-assets/images/people-group.svg",
                "in_create_form" => false,
            ];


            // location
            $fields['location_grid'] = [
                'name'        => __( 'Locations', 'disciple-tools-streams' ),
                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple-tools-streams' ),
                'type'        => 'location',
                'mapbox'    => false,
                "in_create_form" => true,
                "tile" => "details",
                "icon" => get_template_directory_uri() . "/dt-assets/images/location.svg",
            ];
            $fields['location_grid_meta'] = [
                'name'        => __( 'Locations', 'disciple-tools-streams' ), //system string does not need translation
                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple-tools-streams' ),
                'type'        => 'location_meta',
                "tile"      => "details",
                'mapbox'    => false,
                'hidden' => true,
                "icon" => get_template_directory_uri() . "/dt-assets/images/location.svg?v=2",
            ];
            $fields["contact_address"] = [
                "name" => __( 'Address', 'disciple-tools-streams' ),
                "icon" => get_template_directory_uri() . "/dt-assets/images/house.svg",
                "type" => "communication_channel",
                "tile" => "details",
                'mapbox'    => false,
                "customizable" => false
            ];
            if ( DT_Mapbox_API::get_key() ){
                $fields["contact_address"]["custom_display"] = true;
                $fields["contact_address"]["mapbox"] = true;
                unset( $fields["contact_address"]["tile"] );
                $fields["location_grid"]["mapbox"] = true;
                $fields["location_grid_meta"]["mapbox"] = true;
                $fields["location_grid"]["hidden"] = true;
                $fields["location_grid_meta"]["hidden"] = false;
            }


            // connection fields
            $fields['leader_total'] = [
                'name' => __( "Number of Leaders", 'disciple-tools-streams' ),
                'type' => 'number',
                'default' => '0',
                'tile' => '',
                'show_in_table' => true,
                'icon' => get_template_directory_uri() . "/dt-assets/images/contact-generation.svg",
            ];
            $fields["leaders"] = [
                "name" => __( 'Leaders', 'disciple-tools-streams' ),
                'description' => '',
                "type" => "connection",
                "post_type" => "contacts",
                'tile' => 'connections',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_leaders",
                'icon' => get_template_directory_uri() . "/dt-assets/images/contact-generation.svg",
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-contact.svg',
                "in_create_form" => true,
            ];
            $fields['disciple_total'] = [
                'name' => __( "Number of Disciples", 'disciple-tools-streams' ),
                'type' => 'number',
                'default' => '0',
                'tile' => '',
                'icon' => get_template_directory_uri() . "/dt-assets/images/contact-generation.svg",
                'show_in_table' => true
            ];
            $fields['disciples'] = [
                'name' => __( "Key Disciples", 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => 'contacts',
                'tile' => 'connections',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_disciples",
                'icon' => get_template_directory_uri() . "/dt-assets/images/contact-generation.svg",
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-contact.svg',
                "in_create_form" => true,
            ];
            $fields['group_total'] = [
                'name' => __( "Number of Churches", 'disciple-tools-streams' ),
                'type' => 'number',
                'default' => '0',
                'tile' => '',
                'icon' => get_template_directory_uri() . "/dt-assets/images/groups.svg",
                'show_in_table' => true
            ];
            $fields['groups'] = [
                'name' => __( "Groups", 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => 'groups',
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_groups",
                "tile" => "connections",
                'icon' => get_template_directory_uri() . "/dt-assets/images/groups.svg",
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
                "in_create_form" => true,
            ];
//            $fields['generations_total'] = [
//                'name' => __( "Highest Generation", 'disciple-tools-streams' ),
//                'type' => 'number',
//                'default' => '0',
//                'tile' => 'stats',
//                'icon' => get_template_directory_uri() . "/dt-assets/images/groups.svg",
//                'show_in_table' => true
//            ];



            // parent child fields
            $fields["parent_streams"] = [
                "name" => __( 'Parent Stream', 'disciple-tools-streams' ),
                'description' => _x( 'A stream that launched this stream.', 'Optional Documentation', 'disciple-tools-streams' ),
                "type" => "connection",
                "post_type" => "streams",
                "p2p_direction" => "from",
                "p2p_key" => "streams_to_streams",
                'tile' => 'connections',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-parent.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add.svg',
                "in_create_form" => true,
            ];
            $fields["peer_streams"] = [
                "name" => __( 'Peer Streams', 'disciple-tools-streams' ),
                'description' => _x( "A related stream that isn't a parent/child in relationship. It might indicate streams that collaborate, are about to merge, recently split, etc.", 'Optional Documentation', 'disciple-tools-streams' ),
                "type" => "connection",
                "post_type" => "streams",
                "p2p_direction" => "any",
                "p2p_key" => "streams_to_peers",
                'tile' => 'connections',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-peer.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add.svg',
                "in_create_form" => true,
            ];
            $fields["child_streams"] = [
                "name" => __( 'Child Streams', 'disciple-tools-streams' ),
                'description' => _x( 'A stream that has been birthed out of this stream.', 'Optional Documentation', 'disciple-tools-streams' ),
                "type" => "connection",
                "post_type" => "streams",
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_streams",
                'tile' => 'connections',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-child.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add.svg',
                "in_create_form" => true,
            ];


            if ( $this->trainings ) {
                $fields['training_total'] = [
                    'name' => __( "Trainings Total", 'disciple-tools-streams' ),
                    'type' => 'number',
                    'default' => '0',
                    'tile' => 'totals',
                    'show_in_table' => false
                ];
                $fields['trainings'] = [
                    'name' => __( "Trainings", 'disciple-tools-streams' ),
                    'type' => 'connection',
                    "post_type" => 'trainings',
                    'tile' => 'connections',
                    "p2p_direction" => "from",
                    "p2p_key" => "streams_to_trainings",
                    'icon' => get_template_directory_uri() . '/dt-assets/images/trainings.svg',
                    'create-icon' => get_template_directory_uri() . '/dt-assets/images/trainings-hollow.svg',
                    "in_create_form" => true,
                ];
            }
        }

        if ( $post_type === 'contacts' ){
            $fields['stream_leader'] = [
                'name' => __( "Leader in Stream", 'disciple-tools-streams' ),
                'description' => _x( 'Leader of a stream', 'Optional Documentation', 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => $this->post_type,
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_leaders",
                "tile" => "other",
                'icon' => get_template_directory_uri() . "/dt-assets/images/stream.svg",
                'create-icon' => get_template_directory_uri() . "/dt-assets/images/add.svg",
            ];
            $fields['stream_disciple'] = [
                'name' => __( "Key Disciple in Stream", 'disciple-tools-streams' ),
                'description' => _x( 'Disciple in a stream.', 'Optional Documentation', 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => $this->post_type,
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_disciples",
                "tile" => "other",
                'icon' => get_template_directory_uri() . "/dt-assets/images/stream.svg",
                'create-icon' => get_template_directory_uri() . "/dt-assets/images/add.svg",
            ];
        }
        if ( $post_type === 'groups' ){
            $fields[$this->post_type] = [
                'name' => __( "Streams", 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => $this->post_type,
                "p2p_direction" => "to",
                "p2p_key" => "streams_to_groups",
                "tile" => "other",
                'icon' => get_template_directory_uri() . "/dt-assets/images/stream.svg",
                'create-icon' => get_template_directory_uri() . "/dt-assets/images/add.svg",
            ];
        }
        if ( $post_type === 'trainings' ){
            $fields['streams'] = [
                'name' => __( "Streams", 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => 'streams',
                "p2p_direction" => "to",
                "tile" => 'other',
                "p2p_key" => "streams_to_trainings",
                'icon' => get_template_directory_uri() . "/dt-assets/images/stream.svg",
                'create-icon' => get_template_directory_uri() . "/dt-assets/images/add.svg",
            ];
        }
        if ( $post_type === 'peoplegroups' ){
            $fields['peoplegroups'] = [
                'name' => __( "Streams", 'disciple-tools-streams' ),
                'type' => 'connection',
                "post_type" => 'peoplegroups',
                "p2p_direction" => "to",
                "tile" => 'other',
                "p2p_key" => "streams_to_peoplegroups",
                'icon' => get_template_directory_uri() . "/dt-assets/images/stream.svg",
                'create-icon' => get_template_directory_uri() . "/dt-assets/images/add.svg",
            ];
        }

        return $fields;
    }

    /**
     * Set the singular and plural translations for this post types settings
     * The add_filter is set onto a higher priority than the one in Disciple_tools_Post_Type_Template
     * so as to enable localisation changes. Otherwise the system translation passed in to the custom post type
     * will prevail.
     */
    public function dt_get_post_type_settings( $settings, $post_type ){
        if ( $post_type === $this->post_type ){
            $settings['label_singular'] = __( 'Stream', 'disciple-tools-streams' );
            $settings['label_plural'] = __( 'Streams', 'disciple-tools-streams' );
            $settings['status_field'] = [
                "status_key" => "status",
                "archived_key" => "closed",
            ];
        }
        return $settings;
    }

    public function p2p_init(){
        /**
         * Stream contacts field
         */
        p2p_register_connection_type(
            [
                'name'           => 'streams_to_disciples',
                'from'           => 'streams',
                'to'             => 'contacts',
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Members', 'disciple-tools-streams' ),
                    'to'   => __( 'Contacts', 'disciple-tools-streams' ),
                ]
            ]
        );
        /**
         * Stream to groups
         */
        p2p_register_connection_type(
            [
                'name'           => 'streams_to_groups',
                'from'           => 'streams',
                'to'             => 'groups',
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Streams', 'disciple-tools-streams' ),
                    'to'   => __( 'Groups', 'disciple-tools-streams' ),
                ]
            ]
        );
        /**
         * Stream leaders field
         */
        p2p_register_connection_type(
            [
                'name'           => 'streams_to_leaders',
                'from'           => 'streams',
                'to'             => 'contacts',
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Streams', 'disciple-tools-streams' ),
                    'to'   => __( 'Leaders', 'disciple-tools-streams' ),
                ]
            ]
        );
        /**
         * Stream coaches field
         */
        p2p_register_connection_type(
            [
                'name'           => 'streams_to_coaches',
                'from'           => 'streams',
                'to'             => 'contacts',
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Streams', 'disciple-tools-streams' ),
                    'to'   => __( 'Coaches', 'disciple-tools-streams' ),
                ]
            ]
        );
        /**
         * Parent and child streams
         */
        p2p_register_connection_type(
            [
                'name'         => 'streams_to_streams',
                'from'         => 'streams',
                'to'           => 'streams',
                'title'        => [
                    'from' => __( 'Planted by', 'disciple-tools-streams' ),
                    'to'   => __( 'Planting', 'disciple-tools-streams' ),
                ],
            ]
        );
        /**
         * Peer streams
         */
        p2p_register_connection_type( [
            'name'         => 'streams_to_peers',
            'from'         => 'streams',
            'to'           => 'streams',
        ] );
        /**
         * Stream People Groups field
         */
        p2p_register_connection_type(
            [
                'name'        => 'streams_to_peoplegroups',
                'from'        => 'streams',
                'to'          => 'peoplegroups',
                'title'       => [
                    'from' => __( 'People Groups', 'disciple-tools-streams' ),
                    'to'   => __( 'Streams', 'disciple-tools-streams' ),
                ]
            ]
        );
        p2p_register_connection_type(
            [
                'name'           => 'streams_to_reporter',
                'from'           => 'streams',
                'to'             => 'contacts',
                'admin_box' => [
                    'show' => false,
                ],
                'title'          => [
                    'from' => __( 'Reporter', 'disciple-tools-streams' ),
                    'to'   => __( 'Reporter', 'disciple-tools-streams' ),
                ]
            ]
        );

        if ( $this->trainings ) {
            p2p_register_connection_type([
                'name' => 'streams_to_trainings',
                'from' => 'streams',
                'to' => 'trainings'
            ]);
        }
    }

    public function dt_details_additional_tiles( $tiles, $post_type = "" ){
        if ( $post_type === "streams" ){
//            $tiles["stats"] = [ "label" => __( "Stats", 'disciple-tools-streams' ) ];
            $tiles["connections"] = [ "label" => __( "Connections", 'disciple-tools-streams' ) ];
            $tiles["other"] = [ "label" => __( "Other", 'disciple-tools-streams' ) ];
        }
        return $tiles;
    }

    public function dt_details_additional_section( $section, $post_type ){

    }

    //action when a post connection is added during create or update
    public function post_connection_added( $post_type, $post_id, $field_key, $value ){
        if ( $post_type === "streams" ){
            if ( $field_key === "leaders" || $field_key === "disciples" || $field_key === "groups" ){

                // share the stream with the owner of the contact when a disciple is added to a stream
                $assigned_to = get_post_meta( $value, "assigned_to", true );
                if ( $assigned_to && strpos( $assigned_to, "-" ) !== false ){
                    $user_id = explode( "-", $assigned_to )[1];
                    if ( $user_id ){
                        DT_Posts::add_shared( $post_type, $post_id, $user_id, null, false, false );
                    }
                }

                if ( $field_key === "leaders" ){
                    self::update_stream_leader_total( $post_id );
                }
                if ( $field_key === "disciples" ){
                    self::update_stream_disciple_total( $post_id );
                }
                if ( $field_key === "groups" ){
                    self::update_stream_group_total( $post_id );
                }
            }

            if ( $field_key === "trainings" ){

                // share the stream with the owner of the contact when a disciple is added to a stream
                $assigned_to = get_post_meta( $value, "assigned_to", true );
                if ( $assigned_to && strpos( $assigned_to, "-" ) !== false ){
                    $user_id = explode( "-", $assigned_to )[1];
                    if ( $user_id ){
                        DT_Posts::add_shared( $post_type, $post_id, $user_id, null, false, false );
                    }
                }

                self::update_stream_training_total( $post_id );
            }

            if ( $field_key === "coaches" ){
                // share the stream with the coach when a coach is added.
                $user_id = get_post_meta( $value, "corresponds_to_user", true );
                if ( $user_id ){
                    DT_Posts::add_shared( "streams", $post_id, $user_id, null, false, false, false );
                }
            }
        }
        if ( $post_type === "contacts" && $field_key === "streams" ){
            self::update_stream_disciple_total( $value );
            // share the stream with the owner of the contact.
            $assigned_to = get_post_meta( $post_id, "assigned_to", true );
            if ( $assigned_to && strpos( $assigned_to, "-" ) !== false ){
                $user_id = explode( "-", $assigned_to )[1];
                if ( $user_id ){
                    DT_Posts::add_shared( "streams", $value, $user_id, null, false, false );
                }
            }
        }
    }

    //action when a post connection is removed during create or update
    public function post_connection_removed( $post_type, $post_id, $field_key, $value ){
        if ( $post_type === "streams" ){
            if ( $field_key === "leaders" ){
                self::update_stream_leader_total( $post_id, "removed" );
            }
            if ( $field_key === "disciples" ){
                self::update_stream_disciple_total( $post_id, "removed" );
            }
        }
        if ( $post_type === "contacts" && $field_key === "streams" ){
            self::update_stream_disciple_total( $value, "removed" );
        }
    }

    //filter at the start of post update
    public function dt_post_update_fields( $fields, $post_type, $post_id ){
        if ( $post_type === "streams" ){
            if ( isset( $fields["assigned_to"] ) ) {
                if ( filter_var( $fields["assigned_to"], FILTER_VALIDATE_EMAIL ) ){
                    $user = get_user_by( "email", $fields["assigned_to"] );
                    if ( $user ) {
                        $fields["assigned_to"] = $user->ID;
                    } else {
                        return new WP_Error( __FUNCTION__, "Unrecognized user", $fields["assigned_to"] );
                    }
                }
                //make sure the assigned to is in the right format (user-1)
                if ( is_numeric( $fields["assigned_to"] ) ||
                    strpos( $fields["assigned_to"], "user" ) === false ){
                    $fields["assigned_to"] = "user-" . $fields["assigned_to"];
                }
                $user_id = dt_get_user_id_from_assigned_to( $fields["assigned_to"] );
                if ( $user_id ){
                    DT_Posts::add_shared( "streams", $post_id, $user_id, null, false, true, false );
                }
            }
        }
        return $fields;
    }

    private static function update_stream_leader_total( $stream_id, $action = "added" ){
        $stream = get_post( $stream_id );
        $args = [
            'connected_type'   => "streams_to_leaders",
            'connected_direction' => 'from',
            'connected_items'  => $stream,
            'nopaging'         => true,
            'suppress_filters' => false,
        ];
        $leaders = get_posts( $args );
        $leader_total = get_post_meta( $stream_id, 'leader_total', true );
        if ( sizeof( $leaders ) > intval( $leader_total ) ){
            update_post_meta( $stream_id, 'leader_total', sizeof( $leaders ) );
        } elseif ( $action === "removed" ){
            update_post_meta( $stream_id, 'leader_total', intval( $leader_total - 1 ) );
        }
    }

    //update the stream disciple total when contacts are added or removed.
    private static function update_stream_disciple_total( $stream_id, $action = "added" ){
        $stream = get_post( $stream_id );
        $args = [
            'connected_type'   => "streams_to_disciples",
            'connected_direction' => 'from',
            'connected_items'  => $stream,
            'nopaging'         => true,
            'suppress_filters' => false,
        ];
        $contacts = get_posts( $args );
        $disciple_total = get_post_meta( $stream_id, 'disciple_total', true );
        if ( sizeof( $contacts ) > intval( $disciple_total ) ){
            update_post_meta( $stream_id, 'disciple_total', sizeof( $contacts ) );
        } elseif ( $action === "removed" ){
            update_post_meta( $stream_id, 'disciple_total', intval( $disciple_total ) - 1 );
        }
    }

    private static function update_stream_group_total( $id, $action = "added" ){
        $this_post = get_post( $id );
        $args = [
            'connected_type'   => "streams_to_groups",
            'connected_direction' => 'from',
            'connected_items'  => $this_post,
            'nopaging'         => true,
            'suppress_filters' => false,
        ];
        $posts_list = get_posts( $args );
        $total = get_post_meta( $id, 'group_total', true );
        if ( sizeof( $posts_list ) > intval( $total ) ){
            update_post_meta( $id, 'group_total', sizeof( $posts_list ) );
        } elseif ( $action === "removed" ){
            update_post_meta( $id, 'group_total', intval( $total - 1 ) );
        }
    }

    private static function update_stream_training_total( $stream_id, $action = "added" ){
        $stream = get_post( $stream_id );
        $args = [
            'connected_type'   => "streams_to_trainings",
            'connected_direction' => 'from',
            'connected_items'  => $stream,
            'nopaging'         => true,
            'suppress_filters' => false,
        ];
        $trainings = get_posts( $args );
        $training_total = get_post_meta( $stream_id, 'training_total', true );
        if ( sizeof( $trainings ) > intval( $training_total ) ){
            update_post_meta( $stream_id, 'training_total', sizeof( $trainings ) );
        } elseif ( $action === "removed" ){
            update_post_meta( $stream_id, 'training_total', intval( $training_total - 1 ) );
        }
    }

    //check to see if the stream is marked as needing an update
    //if yes: mark as updated
    private static function check_requires_update( $stream_id ){
        if ( get_current_user_id() ){
            $requires_update = get_post_meta( $stream_id, "requires_update", true );
            if ( $requires_update == "yes" || $requires_update == true || $requires_update == "1"){
                //don't remove update needed if the user is a dispatcher (and not assigned to the streams.)
                if ( DT_Posts::can_view_all( 'streams' ) ){
                    if ( dt_get_user_id_from_assigned_to( get_post_meta( $stream_id, "assigned_to", true ) ) === get_current_user_id() ){
                        update_post_meta( $stream_id, "requires_update", false );
                    }
                } else {
                    update_post_meta( $stream_id, "requires_update", false );
                }
            }
        }
    }

    //filter when a comment is created
    public function dt_comment_created( $post_type, $post_id, $comment_id, $type ){
        if ( $post_type === "streams" ){
            if ( $type === "comment" ){
                self::check_requires_update( $post_id );
            }
        }
    }

    // filter at the start of post creation
    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === "streams" ) {
            if ( !isset( $fields["status"] ) ) {
                $fields["status"] = "new";
            }
            if ( !isset( $fields["assigned_to"] ) ) {
                $fields["assigned_to"] = sprintf( "user-%d", get_current_user_id() );
            }
            if ( isset( $fields["assigned_to"] ) ) {
                if ( filter_var( $fields["assigned_to"], FILTER_VALIDATE_EMAIL ) ){
                    $user = get_user_by( "email", $fields["assigned_to"] );
                    if ( $user ) {
                        $fields["assigned_to"] = $user->ID;
                    } else {
                        return new WP_Error( __FUNCTION__, "Unrecognized user", $fields["assigned_to"] );
                    }
                }
                //make sure the assigned to is in the right format (user-1)
                if ( is_numeric( $fields["assigned_to"] ) ||
                    strpos( $fields["assigned_to"], "user" ) === false ){
                    $fields["assigned_to"] = "user-" . $fields["assigned_to"];
                }
            }
        }
        return $fields;
    }

    //action when a post has been created
    public function dt_post_created( $post_type, $post_id, $initial_fields ){
        if ( $post_type === "streams" ){
            do_action( "dt_stream_created", $post_id, $initial_fields );
            $stream = DT_Posts::get_post( 'streams', $post_id, true, false );
            if ( isset( $stream["assigned_to"] )) {
                if ( $stream["assigned_to"]["id"] ) {
                    DT_Posts::add_shared( "streams", $post_id, $stream["assigned_to"]["id"], null, false, false, false );
                }
            }
        }
    }


    //list page filters function
    private static function get_my_streams_status_type(){
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT status.meta_value as status, COUNT(pm.post_id) as count, COUNT(un.post_id) as update_needed
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
            INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = 'streams' and a.post_status = 'publish' )
            INNER JOIN $wpdb->postmeta as assigned_to ON a.ID=assigned_to.post_id
              AND assigned_to.meta_key = 'assigned_to'
              AND assigned_to.meta_value = CONCAT( 'user-', %s )
            LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
            WHERE pm.meta_key = 'status'
            GROUP BY status.meta_value, pm.meta_value
        ", get_current_user_id() ), ARRAY_A);

        return $results;
    }

    //list page filters function
    private static function get_all_streams_status_type(){
        global $wpdb;
        if ( current_user_can( 'view_any_streams' ) ){
            $results = $wpdb->get_results("
                SELECT status.meta_value as status, COUNT(pm.post_id) as count, COUNT(un.post_id) as update_needed
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
                INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = 'streams' and a.post_status = 'publish' )
                LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
                WHERE pm.meta_key = 'status'
                GROUP BY status.meta_value, pm.meta_value
            ", ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT status.meta_value as status, COUNT(pm.post_id) as count, COUNT(un.post_id) as update_needed
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
                INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = 'streams' and a.post_status = 'publish' )
                LEFT JOIN $wpdb->dt_share AS shares ON ( shares.post_id = a.ID AND shares.user_id = %s )
                LEFT JOIN $wpdb->postmeta assigned_to ON ( assigned_to.post_id = pm.post_id AND assigned_to.meta_key = 'assigned_to' && assigned_to.meta_value = %s )
                LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
                WHERE pm.meta_key = 'status' AND
                      ( shares.user_id IS NOT NULL OR assigned_to.meta_value IS NOT NULL )
                GROUP BY status.meta_value, pm.meta_value
            ", get_current_user_id(), 'user-' . get_current_user_id() ), ARRAY_A);
        }

        return $results;
    }

    //build list page filters
    public static function dt_user_list_filters( $filters, $post_type ){
        if ( $post_type === 'streams' ){
            $totals = self::get_my_streams_status_type();
            $fields = DT_Posts::get_post_field_settings( $post_type );
            /**
             * Setup my stream filters
             */
            $active_totals = [];
            $update_needed = 0;
            $status_totals = [];
            $total_my = 0;
            foreach ( $totals as $total ){
                $total_my += $total["count"];
                dt_increment( $status_totals[$total["status"]], $total["count"] );
                if ( $total["status"] === "new" ){
                    if ( isset( $total["update_needed"] ) ) {
                        $update_needed += (int) $total["update_needed"];
                    }
                    dt_increment( $active_totals[$total["status"]], $total["count"] );
                }
            }


            $filters["tabs"][] = [
                "key" => "assigned_to_me",
                "label" => _x( "Assigned to me", 'List Filters', 'disciple-tools-streams' ),
                "count" => $total_my,
                "order" => 20
            ];
            // add assigned to me filters
            $filters["filters"][] = [
                'ID' => 'my_all',
                'tab' => 'assigned_to_me',
                'name' => _x( "All", 'List Filters', 'disciple-tools-streams' ),
                'query' => [
                    'assigned_to' => [ 'me' ],
                    'sort' => '-post_date',
                    'status' => [ '-closed' ]
                ],
                "count" => $total_my,
            ];

            foreach ( $fields["status"]["default"] as $status_key => $status_value ) {
                if ( isset( $status_totals[$status_key] ) ){
                    $filters["filters"][] = [
                        "ID" => 'my_' . $status_key,
                        "tab" => 'assigned_to_me',
                        "name" => $status_value["label"],
                        "query" => [
                            'assigned_to' => [ 'me' ],
                            'status' => [ $status_key ],
                            'sort' => '-post_date'
                        ],
                        "count" => $status_totals[$status_key]
                    ];
                    if ( $status_key === "new" ){
                        if ( $update_needed > 0 ){
                            $filters["filters"][] = [
                                "ID" => 'my_update_needed',
                                "tab" => 'assigned_to_me',
                                "name" => $fields["requires_update"]["name"],
                                "query" => [
                                    'assigned_to' => [ 'me' ],
                                    'status' => [ 'new' ],
                                    'requires_update' => [ true ],
                                ],
                                "count" => $update_needed,
                                'subfilter' => true
                            ];
                        }
                    }
                }
            }

            if ( current_user_can( 'view_all_streams' ) ){
                $totals = self::get_all_streams_status_type();
                $active_totals = [];
                $update_needed = 0;
                $status_totals = [];
                $total_all = 0;
                foreach ( $totals as $total ){
                    $total_all += $total["count"];
                    dt_increment( $status_totals[$total["status"]], $total["count"] );
                    if ( $total["status"] === "new" ){
                        if ( isset( $total["update_needed"] ) ){
                            $update_needed += (int) $total["update_needed"];
                        }
                        dt_increment( $active_totals[$total["status"]], $total["count"] );
                    }
                }
                $filters["tabs"][] = [
                    "key" => "all",
                    "label" => _x( "All", 'List Filters', 'disciple-tools-streams' ),
                    "count" => $total_all,
                    "order" => 10
                ];
                // add assigned to me filters
                $filters["filters"][] = [
                    'ID' => 'all',
                    'tab' => 'all',
                    'name' => _x( "All", 'List Filters', 'disciple-tools-streams' ),
                    'query' => [
                        'sort' => '-post_date',
                        'status' => [ '-closed' ]
                    ],
                    "count" => $total_all
                ];

                foreach ( $fields["status"]["default"] as $status_key => $status_value ){
                    if ( isset( $status_totals[$status_key] ) ){
                        $filters["filters"][] = [
                            "ID" => 'all_' . $status_key,
                            "tab" => 'all',
                            "name" => $status_value["label"],
                            "query" => [
                                'status' => [ $status_key ],
                                'sort' => '-post_date'
                            ],
                            "count" => $status_totals[$status_key]
                        ];
                        if ( $status_key === "new" ){
                            if ( $update_needed > 0 ){
                                $filters["filters"][] = [
                                    "ID" => 'all_update_needed',
                                    "tab" => 'all',
                                    "name" => $fields["requires_update"]["name"],
                                    "query" => [
                                        'status' => [ 'new' ],
                                        'requires_update' => [ true ],
                                    ],
                                    "count" => $update_needed,
                                    'subfilter' => true
                                ];
                            }
                        }
                    }
                }
            }
        }
        return $filters;
    }

    public function scripts(){
        if ( is_singular( "streams" ) && get_the_ID() && DT_Posts::can_view( $this->post_type, get_the_ID() ) ){
            wp_enqueue_script( 'dt_streams', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'streams-post-type.js', [
                'jquery',
                'details',
                'typeahead-jquery',
                'shared-functions',
            ], filemtime( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'streams-post-type.js' ), true );
        }
    }

    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === "streams" ){
            if ( DT_Posts::can_view_all( $post_type ) ){
                $permissions = [];
            }
        }
        return $permissions;
    }
}
