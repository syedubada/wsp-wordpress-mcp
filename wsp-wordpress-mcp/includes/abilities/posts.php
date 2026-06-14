<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_posts_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/get-posts' ) ) {
        wp_register_ability( 'wsp/get-posts', array_merge( $base, array(
            'label'              => 'Get Blog Posts',
            'description'        => 'Returns blog posts with full metadata.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(
                'per_page' => array( 'type' => 'integer', 'description' => 'Number of posts. Default 10.' ),
                'status'   => array( 'type' => 'string',  'description' => 'publish | draft | all. Default publish.' ),
            ) ),
            'permission_callback' => '__return_true',
            'execute_callback'   => 'wsp_execute_get_posts',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/create-post' ) ) {
        wp_register_ability( 'wsp/create-post', array_merge( $base, array(
            'label'              => 'Create Post',
            'description'        => 'Creates a new blog post.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'title', 'content' ), 'properties' => array(
                'title'      => array( 'type' => 'string',  'description' => 'Post title.' ),
                'content'    => array( 'type' => 'string',  'description' => 'Post content (HTML).' ),
                'status'     => array( 'type' => 'string',  'description' => 'publish | draft | pending. Default draft.' ),
                'categories' => array( 'type' => 'array',   'items' => array( 'type' => 'integer' ), 'description' => 'Category IDs.' ),
                'tags'       => array( 'type' => 'array',   'items' => array( 'type' => 'integer' ), 'description' => 'Tag IDs.' ),
                'excerpt'    => array( 'type' => 'string',  'description' => 'Post excerpt.' ),
                'slug'       => array( 'type' => 'string',  'description' => 'URL slug.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'publish_posts' ); },
            'execute_callback'   => 'wsp_execute_create_post',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/update-post' ) ) {
        wp_register_ability( 'wsp/update-post', array_merge( $base, array(
            'label'              => 'Update Post',
            'description'        => 'Updates an existing post.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
                'id'         => array( 'type' => 'integer', 'description' => 'Post ID.' ),
                'title'      => array( 'type' => 'string',  'description' => 'New title.' ),
                'content'    => array( 'type' => 'string',  'description' => 'New content.' ),
                'status'     => array( 'type' => 'string',  'description' => 'New status.' ),
                'categories' => array( 'type' => 'array',   'items' => array( 'type' => 'integer' ) ),
                'tags'       => array( 'type' => 'array',   'items' => array( 'type' => 'integer' ) ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'execute_callback'   => 'wsp_execute_update_post',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/delete-post' ) ) {
        wp_register_ability( 'wsp/delete-post', array_merge( $base, array(
            'label'              => 'Delete Post',
            'description'        => 'Moves a post to trash.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
                'id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'delete_posts' ); },
            'execute_callback'   => 'wsp_execute_delete_post',
        ) ) );
    }
}

function wsp_execute_get_posts( $input ) {
    $per_page = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 10;
    $status   = isset( $input['status'] )   ? sanitize_text_field( wp_unslash( $input['status'] ) ) : 'publish';
    if ( 'all' === $status ) $status = array( 'publish', 'draft', 'pending', 'future' );
    $q     = new WP_Query( array( 'post_status' => $status, 'posts_per_page' => $per_page, 'orderby' => 'date', 'order' => 'DESC' ) );
    $posts = array();
    foreach ( $q->posts as $p ) {
        $posts[] = array(
            'id'         => $p->ID,
            'title'      => $p->post_title,
            'url'        => get_permalink( $p->ID ),
            'status'     => $p->post_status,
            'date'       => get_the_date( 'Y-m-d', $p->ID ),
            'author'     => get_the_author_meta( 'display_name', $p->post_author ),
            'categories' => wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) ),
            'tags'       => wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) ),
            'excerpt'    => has_excerpt( $p->ID ) ? get_the_excerpt( $p ) : wp_trim_words( $p->post_content, 40 ),
        );
    }
    return array( 'posts' => $posts, 'total' => $q->found_posts );
}

function wsp_execute_create_post( $input ) {
    $args = array(
        'post_title'   => sanitize_text_field( wp_unslash( $input['title'] ) ),
        'post_content' => wp_kses_post( wp_unslash( $input['content'] ) ),
        'post_status'  => isset( $input['status'] ) ? sanitize_text_field( wp_unslash( $input['status'] ) ) : 'draft',
        'post_type'    => 'post',
    );
    if ( ! empty( $input['excerpt'] ) )    $args['post_excerpt']  = sanitize_text_field( wp_unslash( $input['excerpt'] ) );
    if ( ! empty( $input['slug'] ) )       $args['post_name']     = sanitize_title( $input['slug'] );
    if ( ! empty( $input['categories'] ) ) $args['post_category'] = array_map( 'intval', $input['categories'] );
    $id = wp_insert_post( $args, true );
    if ( is_wp_error( $id ) ) return array( 'success' => false, 'error' => $id->get_error_message() );
    if ( ! empty( $input['tags'] ) ) wp_set_post_tags( $id, array_map( 'intval', $input['tags'] ) );
    return array( 'success' => true, 'id' => $id, 'url' => get_permalink( $id ), 'status' => $args['post_status'] );
}

function wsp_execute_update_post( $input ) {
    $args = array( 'ID' => intval( $input['id'] ) );
    if ( isset( $input['title'] ) )      $args['post_title']    = sanitize_text_field( wp_unslash( $input['title'] ) );
    if ( isset( $input['content'] ) )    $args['post_content']  = wp_kses_post( wp_unslash( $input['content'] ) );
    if ( isset( $input['status'] ) )     $args['post_status']   = sanitize_text_field( wp_unslash( $input['status'] ) );
    if ( isset( $input['categories'] ) ) $args['post_category'] = array_map( 'intval', $input['categories'] );
    $id = wp_update_post( $args, true );
    if ( is_wp_error( $id ) ) return array( 'success' => false, 'error' => $id->get_error_message() );
    if ( isset( $input['tags'] ) ) wp_set_post_tags( $id, array_map( 'intval', $input['tags'] ) );
    return array( 'success' => true, 'id' => $id, 'url' => get_permalink( $id ) );
}

function wsp_execute_delete_post( $input ) {
    $id = intval( $input['id'] );
    return wp_trash_post( $id )
        ? array( 'success' => true,  'message' => "Post {$id} moved to trash." )
        : array( 'success' => false, 'error'   => 'Could not trash post.' );
}
