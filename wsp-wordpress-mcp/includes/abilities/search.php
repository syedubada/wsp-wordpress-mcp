<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_search_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/search' ) ) {
        wp_register_ability( 'wsp/search', array_merge( $base, array(
            'label'              => 'Search Content',
            'description'        => 'Search posts and pages by keyword.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'query' ), 'properties' => array(
                'query' => array( 'type' => 'string', 'description' => 'Search keyword.' ),
            ) ),
            'permission_callback' => '__return_true',
            'execute_callback'   => 'wsp_execute_search',
        ) ) );
    }
}

function wsp_execute_search( $input ) {
    $keyword = isset( $input['query'] ) ? sanitize_text_field( wp_unslash( $input['query'] ) ) : '';
    $q       = new WP_Query( array( 's' => $keyword, 'post_status' => 'publish', 'posts_per_page' => 10 ) );
    $results = array();
    foreach ( $q->posts as $p ) {
        $results[] = array( 'id' => $p->ID, 'title' => $p->post_title, 'url' => get_permalink( $p->ID ), 'type' => $p->post_type );
    }
    return array( 'results' => $results, 'total' => $q->found_posts );
}
