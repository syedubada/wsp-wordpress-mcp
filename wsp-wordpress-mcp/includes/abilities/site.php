<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_site_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/get-site-info' ) ) {
        wp_register_ability( 'wsp/get-site-info', array_merge( $base, array(
            'label'              => 'Get Site Info',
            'description'        => 'Returns site metadata.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array() ),
            'permission_callback' => '__return_true',
            'execute_callback'   => 'wsp_execute_get_site_info',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/get-plugins' ) ) {
        wp_register_ability( 'wsp/get-plugins', array_merge( $base, array(
            'label'              => 'Get Active Plugins',
            'description'        => 'Lists active plugins.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array() ),
            'permission_callback' => function() { return current_user_can( 'activate_plugins' ); },
            'execute_callback'   => 'wsp_execute_get_plugins',
        ) ) );
    }
}

function wsp_execute_get_site_info( $input ) {
    return array(
        'name'        => get_bloginfo( 'name' ),
        'url'         => get_site_url(),
        'tagline'     => get_bloginfo( 'description' ),
        'admin_email' => get_option( 'admin_email' ),
        'wp_version'  => get_bloginfo( 'version' ),
        'language'    => get_bloginfo( 'language' ),
    );
}

function wsp_execute_get_plugins( $input ) {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all    = get_plugins();
    $active = get_option( 'active_plugins', array() );
    $result = array();
    foreach ( $active as $file ) {
        if ( isset( $all[ $file ] ) ) {
            $result[] = array(
                'name'    => $all[ $file ]['Name'],
                'version' => $all[ $file ]['Version'],
                'author'  => $all[ $file ]['Author'],
                'file'    => $file,
            );
        }
    }
    return array( 'active_plugins' => $result, 'total' => count( $result ) );
}
