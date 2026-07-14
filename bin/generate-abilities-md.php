<?php
/**
 * Generate abilities.md (and optionally abilities.json) from the plugin registry.
 *
 * `wsp-mcp-ai-agents-connector/includes/registry.php` is the single source of
 * truth for every MCP tool the plugin exposes. This script executes it in a
 * minimal stub environment (so the plugin-gated groups — Yoast, WooCommerce,
 * Elementor, ACF — are all rendered) and prints the same Markdown table format
 * used on freewordpressmcp.com, or a JSON array for building the site's HTML.
 *
 * This file is DEV TOOLING. It lives outside the plugin folder and is never
 * shipped in the plugin zip. It only reads registry.php; it never modifies it.
 *
 * Usage:
 *   php bin/generate-abilities-md.php          # prints abilities.md to stdout
 *   php bin/generate-abilities-md.php --json    # prints abilities.json to stdout
 *
 * @package WSP_MCP
 */

// --- Minimal stubs so registry.php runs outside WordPress -------------------
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // registry.php bails without this.
}
// Force every plugin-gated group to be included in the output.
if ( ! function_exists( 'wsp_yoast_is_active' ) )     { function wsp_yoast_is_active()     { return true; } }
if ( ! function_exists( 'wsp_elementor_is_active' ) ) { function wsp_elementor_is_active() { return true; } }
if ( ! function_exists( 'wsp_acf_is_active' ) )       { function wsp_acf_is_active()       { return true; } }
if ( ! class_exists( 'WooCommerce' ) )                { class WooCommerce {} }

require __DIR__ . '/../wsp-mcp-ai-agents-connector/includes/registry.php';

if ( ! function_exists( 'wsp_mcp_ability_registry' ) ) {
	fwrite( STDERR, "Error: wsp_mcp_ability_registry() not found after including registry.php.\n" );
	exit( 1 );
}

$abilities = wsp_mcp_ability_registry();

// --- Section config ---------------------------------------------------------
$core_groups = array( 'Posts', 'Pages', 'Taxonomy', 'Comments', 'Media', 'Users', 'Search', 'Site' );

// Plugin group => "requires the X plugin" suffix, in display order.
$plugin_sections = array(
	'Yoast SEO'              => 'requires the Yoast SEO plugin',
	'WooCommerce'            => 'requires the WooCommerce plugin',
	'Elementor'              => 'requires the Elementor plugin',
	'Advanced Custom Fields' => 'requires the ACF plugin',
);

// --- Bucket abilities by group (insertion order preserved) ------------------
$by_group = array();
foreach ( $abilities as $key => $a ) {
	$by_group[ $a['group'] ][ $key ] = $a;
}

// --- Totals -----------------------------------------------------------------
$total = count( $abilities );
$core  = 0;
$read  = 0;
$write = 0;
foreach ( $abilities as $a ) {
	if ( in_array( $a['group'], $core_groups, true ) ) { $core++; }
	if ( 'read' === $a['access'] ) { $read++; } else { $write++; }
}

// --- JSON mode --------------------------------------------------------------
if ( in_array( '--json', $argv, true ) ) {
	$rows = array();
	foreach ( $abilities as $key => $a ) {
		$is_core = in_array( $a['group'], $core_groups, true );
		$rows[]  = array(
			'id'          => $key,
			'name'        => $a['label'],
			'description' => $a['description'],
			'group'       => $a['group'],
			'access'      => $a['access'],
			'default'     => ! empty( $a['default'] ) ? 'on' : 'off',
			'requires'    => $is_core ? null : ( isset( $plugin_sections[ $a['group'] ] ) ? $a['group'] : null ),
		);
	}
	echo wp_json_safe_encode(
		array(
			'totals'    => array( 'total' => $total, 'core' => $core, 'read' => $read, 'write' => $write ),
			'abilities' => $rows,
		)
	) . "\n";
	exit( 0 );
}

/**
 * json_encode with pretty print + unescaped slashes/unicode (no WP dependency).
 */
function wp_json_safe_encode( $data ) {
	return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

// --- Markdown render helper -------------------------------------------------
function wsp_render_table( array $rows ) {
	$out  = "| Ability ID | Name | Description | Access | Default |\n";
	$out .= "|---|---|---|---|---|\n";
	foreach ( $rows as $key => $a ) {
		$default = ! empty( $a['default'] ) ? 'on' : 'off';
		$out    .= sprintf(
			"| `%s` | %s | %s | %s | %s |\n",
			$key,
			$a['label'],
			$a['description'],
			$a['access'],
			$default
		);
	}
	return $out;
}

// --- Emit Markdown ----------------------------------------------------------
$md  = "# Abilities (Tool Registry)\n\n";
$md .= "This file is the **source of truth** for the abilities shown on the\n";
$md .= "[Abilities Directory](https://freewordpressmcp.com/abilities-directory) page (`abilities-directory.html`).\n\n";
$md .= "Each ability is one MCP tool the **WSP MCP – AI Agents Connector** plugin exposes to a connected AI agent.\n";
$md .= "The list mirrors the plugin registry at\n";
$md .= "`wsp-mcp-ai-agents-connector/includes/registry.php` in\n";
$md .= "[bilalnaseer/wsp-wordpress-mcp](https://github.com/bilalnaseer/wsp-wordpress-mcp).\n\n";
$md .= "> **Generated file — do not edit by hand.** Produced by `bin/generate-abilities-md.php`; CI regenerates it whenever `registry.php` changes.\n\n";
$md .= "**When this file changes, update the `ABILITIES` array in `abilities-directory.html` to match** (and bump the `lastmod` date for that page in `sitemap.xml`).\n\n";
$md .= "## Legend\n\n";
$md .= "- **Access** — `read` (safe, list/fetch only) or `write` (creates, updates, or deletes site data).\n";
$md .= "- **Default** — `on` means enabled out of the box; everything else is off until the user turns it on in the WordPress dashboard.\n";
$md .= "- **Requires** — core groups are always available; plugin groups appear only when the named plugin is active.\n\n";
$md .= "## Totals\n\n";
$md .= "- **{$total}** total abilities\n";
$md .= "- **{$core}** core (always available)\n";
$md .= "- **{$read}** read · **{$write}** write\n\n";
$md .= "---\n\n";
$md .= "## Core — always available\n\n";

foreach ( $core_groups as $group ) {
	if ( empty( $by_group[ $group ] ) ) {
		continue;
	}
	$md .= "### {$group}\n\n";
	$md .= wsp_render_table( $by_group[ $group ] );
	$md .= "\n";
}

foreach ( $plugin_sections as $group => $requires ) {
	if ( empty( $by_group[ $group ] ) ) {
		continue;
	}
	$md .= "---\n\n";
	$md .= "## {$group} — {$requires}\n\n";
	$md .= wsp_render_table( $by_group[ $group ] );
	$md .= "\n";
}

echo rtrim( $md ) . "\n";
