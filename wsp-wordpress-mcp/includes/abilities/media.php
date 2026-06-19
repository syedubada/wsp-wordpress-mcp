<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_media_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/get-media' ) ) {
        wp_register_ability( 'wsp/get-media', array_merge( $base, array(
            'label'              => 'Get Media',
            'description'        => 'Lists media library items.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(
                'per_page' => array( 'type' => 'integer', 'description' => 'Limit. Default 20.' ),
                'type'     => array( 'type' => 'string',  'description' => 'MIME type filter e.g. image.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'upload_files' ); },
            'execute_callback'   => 'wsp_execute_get_media',
        ) ) );
    }
}

function wsp_execute_get_media( $input ) {
    $per_page = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 20;
    $args     = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => $per_page );
    if ( ! empty( $input['type'] ) ) $args['post_mime_type'] = sanitize_mime_type( $input['type'] );
    $q      = new WP_Query( $args );
    $result = array();
    foreach ( $q->posts as $item ) {
        $result[] = array(
            'id'    => $item->ID,
            'title' => $item->post_title,
            'url'   => wp_get_attachment_url( $item->ID ),
            'type'  => $item->post_mime_type,
            'date'  => get_the_date( 'Y-m-d', $item->ID ),
        );
    }
    return array( 'media' => $result, 'total' => $q->found_posts );
}
