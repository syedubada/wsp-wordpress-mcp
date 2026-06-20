<?php
/**
 * Transport capability helpers.
 *
 * As of v2.0 the plugin ships its own native MCP server, so an MCP transport is
 * always available and no companion plugin is required. These helpers remain to
 * gate the optional dual-mode path: when the WordPress Abilities API is present,
 * the plugin ALSO registers via wp_register_ability() so connections made
 * through the MCP Adapter before v2.0 keep working unchanged.
 *
 * @package WSP_MCP
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether the WordPress Abilities API is loaded.
 *
 * Gates the dual-mode wp_register_ability() registration. Must check the exact
 * function the plugin calls so we never invoke it when it is absent.
 *
 * @return bool
 */
function wsp_mcp_abilities_api_available() {
	return function_exists( 'wp_register_ability' );
}

/**
 * Whether the plugin can serve MCP at all.
 *
 * Always true in v2.0 — the native server provides the transport regardless of
 * any companion plugin. Retained for back-compat and readability.
 *
 * @return bool
 */
function wsp_mcp_transport_available() {
	return true;
}
