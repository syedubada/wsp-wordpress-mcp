<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_taxonomy_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/get-categories' ) ) {
        wp_register_ability( 'wsp/get-categories', array_merge( $base, array(
            'label'              => 'Get Categories',
            'description'        => 'Returns all categories.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array() ),
            'permission_callback' => '__return_true',
            'execute_callback'   => 'wsp_execute_get_categories',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/create-category' ) ) {
        wp_register_ability( 'wsp/create-category', array_merge( $base, array(
            'label'              => 'Create Category',
            'description'        => 'Creates a new category.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'name' ), 'properties' => array(
                'name'        => array( 'type' => 'string',  'description' => 'Category name.' ),
                'description' => array( 'type' => 'string',  'description' => 'Description.' ),
                'parent'      => array( 'type' => 'integer', 'description' => 'Parent category ID.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'execute_callback'   => 'wsp_execute_create_category',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/get-tags' ) ) {
        wp_register_ability( 'wsp/get-tags', array_merge( $base, array(
            'label'              => 'Get Tags',
            'description'        => 'Returns all tags.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array() ),
            'permission_callback' => '__return_true',
            'execute_callback'   => 'wsp_execute_get_tags',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/create-tag' ) ) {
        wp_register_ability( 'wsp/create-tag', array_merge( $base, array(
            'label'              => 'Create Tag',
            'description'        => 'Creates a new tag.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'name' ), 'properties' => array(
                'name'        => array( 'type' => 'string', 'description' => 'Tag name.' ),
                'description' => array( 'type' => 'string', 'description' => 'Description.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'manage_categories' ); },
            'execute_callback'   => 'wsp_execute_create_tag',
        ) ) );
    }
}

function wsp_execute_get_categories( $input ) {
    $cats   = get_categories( array( 'hide_empty' => false ) );
    $result = array();
    foreach ( $cats as $c ) {
        $result[] = array( 'id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'count' => $c->count, 'parent' => $c->parent );
    }
    return array( 'categories' => $result );
}

function wsp_execute_create_category( $input ) {
    $args = array();
    if ( ! empty( $input['description'] ) ) $args['description'] = sanitize_text_field( wp_unslash( $input['description'] ) );
    if ( ! empty( $input['parent'] ) )      $args['parent']      = intval( $input['parent'] );
    $result = wp_insert_term( sanitize_text_field( wp_unslash( $input['name'] ) ), 'category', $args );
    if ( is_wp_error( $result ) ) return array( 'success' => false, 'error' => $result->get_error_message() );
    return array( 'success' => true, 'id' => $result['term_id'], 'name' => sanitize_text_field( wp_unslash( $input['name'] ) ) );
}

function wsp_execute_get_tags( $input ) {
    $tags   = get_tags( array( 'hide_empty' => false ) );
    $result = array();
    foreach ( $tags as $t ) {
        $result[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count );
    }
    return array( 'tags' => $result );
}

function wsp_execute_create_tag( $input ) {
    $args   = ! empty( $input['description'] ) ? array( 'description' => sanitize_text_field( wp_unslash( $input['description'] ) ) ) : array();
    $result = wp_insert_term( sanitize_text_field( wp_unslash( $input['name'] ) ), 'post_tag', $args );
    if ( is_wp_error( $result ) ) return array( 'success' => false, 'error' => $result->get_error_message() );
    return array( 'success' => true, 'id' => $result['term_id'], 'name' => sanitize_text_field( wp_unslash( $input['name'] ) ) );
}
