<?php
/**
 * Native MCP tool registry (Milestone M2).
 *
 * Maps each existing `wsp_execute_*` ability callback to a native MCP tool.
 * The business logic is reused verbatim — only the registration/transport
 * changes. Per-tool exposure still honours the admin toggles via `enable_key`
 * (the same `wsp/...` keys the Abilities-API path uses), so the Settings page
 * controls both transports identically.
 *
 * @package WSP_MCP
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register all native tools with WSP_MCP_Server.
 */
function wsp_mcp_register_native_tools() {
	$obj = array( 'type' => 'object', 'properties' => new stdClass() );

	// ---- Posts ----
	WSP_MCP_Server::register_tool( 'wsp_get_posts', array(
		'description' => 'Returns blog posts with full metadata.',
		'inputSchema' => array( 'type' => 'object', 'properties' => array(
			'per_page' => array( 'type' => 'integer', 'description' => 'Number of posts. Default 10.' ),
			'status'   => array( 'type' => 'string', 'description' => 'publish | draft | all. Default publish.' ),
		) ),
		'callback'    => 'wsp_execute_get_posts',
		'capability'  => '',
		'enable_key'  => 'wsp/get-posts',
	) );
	WSP_MCP_Server::register_tool( 'wsp_create_post', array(
		'description' => 'Creates a new blog post.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'title', 'content' ), 'properties' => array(
			'title'      => array( 'type' => 'string' ),
			'content'    => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'excerpt'    => array( 'type' => 'string' ),
			'slug'       => array( 'type' => 'string' ),
		) ),
		'callback'    => 'wsp_execute_create_post',
		'capability'  => 'publish_posts',
		'enable_key'  => 'wsp/create-post',
	) );
	WSP_MCP_Server::register_tool( 'wsp_update_post', array(
		'description' => 'Updates an existing post by ID.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'content'    => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'callback'    => 'wsp_execute_update_post',
		'capability'  => 'edit_posts',
		'enable_key'  => 'wsp/update-post',
	) );
	WSP_MCP_Server::register_tool( 'wsp_delete_post', array(
		'description' => 'Moves a post to trash by ID.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback'    => 'wsp_execute_delete_post',
		'capability'  => 'delete_posts',
		'enable_key'  => 'wsp/delete-post',
	) );

	// ---- Pages ----
	WSP_MCP_Server::register_tool( 'wsp_get_pages', array(
		'description' => 'Returns published pages.',
		'inputSchema' => $obj,
		'callback'    => 'wsp_execute_get_pages',
		'capability'  => '',
		'enable_key'  => 'wsp/get-pages',
	) );
	WSP_MCP_Server::register_tool( 'wsp_create_page', array(
		'description' => 'Creates a new page (optionally Elementor-initialized).',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'title', 'content' ), 'properties' => array(
			'title'     => array( 'type' => 'string' ),
			'content'   => array( 'type' => 'string' ),
			'status'    => array( 'type' => 'string' ),
			'parent'    => array( 'type' => 'integer' ),
			'slug'      => array( 'type' => 'string' ),
			'elementor' => array( 'type' => 'boolean' ),
		) ),
		'callback'    => 'wsp_execute_create_page',
		'capability'  => 'publish_pages',
		'enable_key'  => 'wsp/create-page',
	) );
	WSP_MCP_Server::register_tool( 'wsp_update_page', array(
		'description' => 'Updates an existing page by ID.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
			'id'      => array( 'type' => 'integer' ),
			'title'   => array( 'type' => 'string' ),
			'content' => array( 'type' => 'string' ),
			'status'  => array( 'type' => 'string' ),
		) ),
		'callback'    => 'wsp_execute_update_page',
		'capability'  => 'edit_pages',
		'enable_key'  => 'wsp/update-page',
	) );
	WSP_MCP_Server::register_tool( 'wsp_delete_page', array(
		'description' => 'Moves a page to trash by ID.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback'    => 'wsp_execute_delete_page',
		'capability'  => 'delete_pages',
		'enable_key'  => 'wsp/delete-page',
	) );

	// ---- Taxonomy ----
	WSP_MCP_Server::register_tool( 'wsp_get_categories', array(
		'description' => 'Returns all categories.',
		'inputSchema' => $obj,
		'callback'    => 'wsp_execute_get_categories',
		'capability'  => '',
		'enable_key'  => 'wsp/get-categories',
	) );
	WSP_MCP_Server::register_tool( 'wsp_create_category', array(
		'description' => 'Creates a new category.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'name' ), 'properties' => array(
			'name'        => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'parent'      => array( 'type' => 'integer' ),
		) ),
		'callback'    => 'wsp_execute_create_category',
		'capability'  => 'manage_categories',
		'enable_key'  => 'wsp/create-category',
	) );
	WSP_MCP_Server::register_tool( 'wsp_get_tags', array(
		'description' => 'Returns all tags.',
		'inputSchema' => $obj,
		'callback'    => 'wsp_execute_get_tags',
		'capability'  => '',
		'enable_key'  => 'wsp/get-tags',
	) );
	WSP_MCP_Server::register_tool( 'wsp_create_tag', array(
		'description' => 'Creates a new tag.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'name' ), 'properties' => array(
			'name'        => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
		) ),
		'callback'    => 'wsp_execute_create_tag',
		'capability'  => 'manage_categories',
		'enable_key'  => 'wsp/create-tag',
	) );

	// ---- Comments ----
	WSP_MCP_Server::register_tool( 'wsp_get_comments', array(
		'description' => 'Returns comments.',
		'inputSchema' => array( 'type' => 'object', 'properties' => array(
			'status'   => array( 'type' => 'string', 'description' => 'hold | approve | all.' ),
			'per_page' => array( 'type' => 'integer' ),
		) ),
		'callback'    => 'wsp_execute_get_comments',
		'capability'  => 'moderate_comments',
		'enable_key'  => 'wsp/get-comments',
	) );
	WSP_MCP_Server::register_tool( 'wsp_approve_comment', array(
		'description' => 'Approves a pending comment by ID.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback'    => 'wsp_execute_approve_comment',
		'capability'  => 'moderate_comments',
		'enable_key'  => 'wsp/approve-comment',
	) );
	WSP_MCP_Server::register_tool( 'wsp_delete_comment', array(
		'description' => 'Trashes a comment by ID.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback'    => 'wsp_execute_delete_comment',
		'capability'  => 'moderate_comments',
		'enable_key'  => 'wsp/delete-comment',
	) );

	// ---- Media / Users / Search / Site ----
	WSP_MCP_Server::register_tool( 'wsp_get_media', array(
		'description' => 'Lists media library items.',
		'inputSchema' => array( 'type' => 'object', 'properties' => array(
			'per_page' => array( 'type' => 'integer' ),
			'type'     => array( 'type' => 'string', 'description' => 'MIME type filter, e.g. image.' ),
		) ),
		'callback'    => 'wsp_execute_get_media',
		'capability'  => 'upload_files',
		'enable_key'  => 'wsp/get-media',
	) );
	WSP_MCP_Server::register_tool( 'wsp_get_users', array(
		'description' => 'Lists registered users.',
		'inputSchema' => $obj,
		'callback'    => 'wsp_execute_get_users',
		'capability'  => 'list_users',
		'enable_key'  => 'wsp/get-users',
	) );
	WSP_MCP_Server::register_tool( 'wsp_search', array(
		'description' => 'Search posts and pages by keyword.',
		'inputSchema' => array( 'type' => 'object', 'required' => array( 'query' ), 'properties' => array(
			'query' => array( 'type' => 'string' ),
		) ),
		'callback'    => 'wsp_execute_search',
		'capability'  => '',
		'enable_key'  => 'wsp/search',
	) );
	WSP_MCP_Server::register_tool( 'wsp_get_site_info', array(
		'description' => 'Returns site name, URL, tagline, WP version, and language.',
		'inputSchema' => $obj,
		'callback'    => 'wsp_execute_get_site_info',
		'capability'  => '',
		'enable_key'  => 'wsp/get-site-info',
	) );
	WSP_MCP_Server::register_tool( 'wsp_get_plugins', array(
		'description' => 'Lists active plugins with name, version, and author.',
		'inputSchema' => $obj,
		'callback'    => 'wsp_execute_get_plugins',
		'capability'  => 'activate_plugins',
		'enable_key'  => 'wsp/get-plugins',
	) );

	// ---- Yoast SEO (only when Yoast is active) ----
	if ( function_exists( 'wsp_yoast_is_active' ) && wsp_yoast_is_active() ) {
		WSP_MCP_Server::register_tool( 'wsp_yoast_get_seo', array(
			'description' => 'Get Yoast SEO title, meta description, and focus keyphrase for a post or page.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
				'id' => array( 'type' => 'integer' ),
			) ),
			'callback'    => 'wsp_execute_yoast_get_seo',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/yoast-get-seo',
		) );
		WSP_MCP_Server::register_tool( 'wsp_yoast_update_seo', array(
			'description' => 'Update Yoast SEO title, meta description, and/or focus keyphrase for a post or page.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array(
				'id'               => array( 'type' => 'integer' ),
				'seo_title'        => array( 'type' => 'string' ),
				'meta_description' => array( 'type' => 'string' ),
				'focus_keyphrase'  => array( 'type' => 'string' ),
			) ),
			'callback'    => 'wsp_execute_yoast_update_seo',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/yoast-update-seo',
		) );
	}

	// ---- Elementor (only when Elementor is active) ----
	if ( function_exists( 'wsp_elementor_is_active' ) && wsp_elementor_is_active() ) {
		WSP_MCP_Server::register_tool( 'wsp_elementor_list_pages', array(
			'description' => 'Lists pages/posts built with Elementor.',
			'inputSchema' => array( 'type' => 'object', 'properties' => array(
				'post_type' => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'per_page'  => array( 'type' => 'integer' ),
			) ),
			'callback'    => 'wsp_execute_elementor_list_pages',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-list-pages',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_get_page', array(
			'description' => 'Get the element tree of an Elementor page by post ID.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array(
				'post_id' => array( 'type' => 'integer' ),
			) ),
			'callback'    => 'wsp_execute_elementor_get_page',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-get-page',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_get_element', array(
			'description' => 'Get all settings for a specific element by post ID and element ID.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id', 'element_id' ), 'properties' => array(
				'post_id'    => array( 'type' => 'integer' ),
				'element_id' => array( 'type' => 'string' ),
			) ),
			'callback'    => 'wsp_execute_elementor_get_element',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-get-element',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_find_element', array(
			'description' => 'Find elements on a page by widget type or settings content.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array(
				'post_id'     => array( 'type' => 'integer' ),
				'widget_type' => array( 'type' => 'string' ),
				'search'      => array( 'type' => 'string' ),
			) ),
			'callback'    => 'wsp_execute_elementor_find_element',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-find-element',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_list_templates', array(
			'description' => 'List Elementor saved templates from the library.',
			'inputSchema' => array( 'type' => 'object', 'properties' => array(
				'type'     => array( 'type' => 'string' ),
				'per_page' => array( 'type' => 'integer' ),
			) ),
			'callback'    => 'wsp_execute_elementor_list_templates',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-list-templates',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_update_element', array(
			'description' => 'Update settings for a widget or container by element ID.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id', 'element_id', 'settings' ), 'properties' => array(
				'post_id'    => array( 'type' => 'integer' ),
				'element_id' => array( 'type' => 'string' ),
				'settings'   => array( 'type' => 'object' ),
			) ),
			'callback'    => 'wsp_execute_elementor_update_element',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-update-element',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_add_widget', array(
			'description' => 'Add a widget to a container or column on an Elementor page.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id', 'widget_type' ), 'properties' => array(
				'post_id'      => array( 'type' => 'integer' ),
				'widget_type'  => array( 'type' => 'string' ),
				'container_id' => array( 'type' => 'string' ),
				'settings'     => array( 'type' => 'object' ),
				'position'     => array( 'type' => 'integer' ),
			) ),
			'callback'    => 'wsp_execute_elementor_add_widget',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-add-widget',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_add_container', array(
			'description' => 'Add a layout container or section to an Elementor page.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array(
				'post_id'   => array( 'type' => 'integer' ),
				'type'      => array( 'type' => 'string' ),
				'parent_id' => array( 'type' => 'string' ),
				'settings'  => array( 'type' => 'object' ),
				'position'  => array( 'type' => 'integer' ),
			) ),
			'callback'    => 'wsp_execute_elementor_add_container',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-add-container',
		) );
		WSP_MCP_Server::register_tool( 'wsp_elementor_remove_element', array(
			'description' => 'Remove a widget or container from an Elementor page by element ID.',
			'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id', 'element_id' ), 'properties' => array(
				'post_id'    => array( 'type' => 'integer' ),
				'element_id' => array( 'type' => 'string' ),
			) ),
			'callback'    => 'wsp_execute_elementor_remove_element',
			'capability'  => 'edit_posts',
			'enable_key'  => 'wsp/elementor-remove-element',
		) );
	}

	/**
	 * Allow add-ons to register additional native MCP tools.
	 *
	 * @param string $server_class The WSP_MCP_Server class name.
	 */
	do_action( 'wsp_mcp_register_tools', 'WSP_MCP_Server' );
}
