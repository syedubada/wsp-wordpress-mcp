<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wsp_register_comments_abilities() {
    $base = array( 'category' => 'wsp', 'output_schema' => array( 'type' => 'object' ), 'meta' => array( 'mcp' => array( 'public' => true ) ) );

    if ( wsp_mcp_is_enabled( 'wsp/get-comments' ) ) {
        wp_register_ability( 'wsp/get-comments', array_merge( $base, array(
            'label'              => 'Get Comments',
            'description'        => 'Returns comments.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(
                'status'   => array( 'type' => 'string',  'description' => 'hold | approve | all.' ),
                'per_page' => array( 'type' => 'integer', 'description' => 'Limit. Default 20.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'moderate_comments' ); },
            'execute_callback'   => 'wsp_execute_get_comments',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/approve-comment' ) ) {
        wp_register_ability( 'wsp/approve-comment', array_merge( $base, array(
            'label'              => 'Approve Comment',
            'description'        => 'Approves a pending comment.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
                'id' => array( 'type' => 'integer', 'description' => 'Comment ID.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'moderate_comments' ); },
            'execute_callback'   => 'wsp_execute_approve_comment',
        ) ) );
    }

    if ( wsp_mcp_is_enabled( 'wsp/delete-comment' ) ) {
        wp_register_ability( 'wsp/delete-comment', array_merge( $base, array(
            'label'              => 'Delete Comment',
            'description'        => 'Trashes a comment.',
            'input_schema'       => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
                'id' => array( 'type' => 'integer', 'description' => 'Comment ID.' ),
            ) ),
            'permission_callback' => function() { return current_user_can( 'moderate_comments' ); },
            'execute_callback'   => 'wsp_execute_delete_comment',
        ) ) );
    }
}

function wsp_execute_get_comments( $input ) {
    $per_page = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 20;
    $status   = isset( $input['status'] )   ? sanitize_text_field( wp_unslash( $input['status'] ) ) : '';
    $args     = array( 'number' => $per_page );
    if ( $status && 'all' !== $status ) $args['status'] = $status;
    $comments = get_comments( $args );
    $result   = array();
    foreach ( $comments as $c ) {
        $result[] = array(
            'id'      => $c->comment_ID,
            'post_id' => $c->comment_post_ID,
            'author'  => $c->comment_author,
            'email'   => $c->comment_author_email,
            'content' => wp_trim_words( $c->comment_content, 20 ),
            'status'  => $c->comment_approved,
            'date'    => $c->comment_date,
        );
    }
    return array( 'comments' => $result, 'total' => count( $result ) );
}

function wsp_execute_approve_comment( $input ) {
    return wp_set_comment_status( intval( $input['id'] ), 'approve' )
        ? array( 'success' => true,  'message' => 'Comment approved.' )
        : array( 'success' => false, 'error'   => 'Failed to approve.' );
}

function wsp_execute_delete_comment( $input ) {
    return wp_trash_comment( intval( $input['id'] ) )
        ? array( 'success' => true,  'message' => 'Comment trashed.' )
        : array( 'success' => false, 'error'   => 'Failed to trash comment.' );
}
