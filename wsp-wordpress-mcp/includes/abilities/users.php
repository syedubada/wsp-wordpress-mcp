<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_users_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/get-users' ) ) {
        wp_register_ability( 'wsp/get-users', array_merge( $base, array(
            'label'              => 'Get Users',
            'description'        => 'Lists registered users.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array() ),
            'permission_callback' => function() { return current_user_can( 'list_users' ); },
            'execute_callback'   => 'wsp_execute_get_users',
        ) ) );
    }
}

function wsp_execute_get_users( $input ) {
    $users  = get_users();
    $result = array();
    foreach ( $users as $u ) {
        $result[] = array(
            'id'           => $u->ID,
            'display_name' => $u->display_name,
            'email'        => $u->user_email,
            'roles'        => $u->roles,
            'registered'   => $u->user_registered,
        );
    }
    return array( 'users' => $result );
}
