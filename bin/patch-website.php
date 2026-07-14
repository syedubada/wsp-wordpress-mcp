<?php
/**
 * Patch the website's abilities-directory.html and sitemap.xml in place from
 * the plugin registry, so the site's tool list never drifts from the plugin.
 *
 * DEV TOOLING. Lives outside the plugin folder, never shipped in the plugin
 * zip. Reads registry.php (via lib-abilities.php); writes only to the two
 * website files passed as arguments.
 *
 * Usage:
 *   php bin/patch-website.php <path/to/abilities-directory.html> <path/to/sitemap.xml>
 *
 * Exit codes: 0 on success, 1 on any failure (so CI stops instead of
 * committing a half-patched site).
 *
 * @package WSP_MCP
 */

require __DIR__ . '/lib-abilities.php';

$html_path    = $argv[1] ?? '';
$sitemap_path = $argv[2] ?? '';

if ( '' === $html_path || ! is_file( $html_path ) ) {
	fwrite( STDERR, "Error: HTML file not found: {$html_path}\n" );
	exit( 1 );
}

/**
 * Escape a value for a single-quoted JS string.
 */
function wsp_js_str( $s ) {
	return str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $s );
}

// --- Build the ABILITIES array body -----------------------------------------
$entries = array();
foreach ( wsp_abilities_all() as $id => $a ) {
	$entries[] = sprintf(
		"      { id: '%s', name: '%s', desc: '%s', group: '%s', access: '%s', def: %s }",
		wsp_js_str( $id ),
		wsp_js_str( $a['label'] ),
		wsp_js_str( $a['description'] ),
		wsp_js_str( $a['group'] ),
		wsp_js_str( $a['access'] ),
		! empty( $a['default'] ) ? 'true' : 'false'
	);
}
$array_body = "\n" . implode( ",\n", $entries ) . "\n    ";

// --- Patch abilities-directory.html -----------------------------------------
$html = file_get_contents( $html_path );
if ( false === $html ) {
	fwrite( STDERR, "Error: could not read {$html_path}\n" );
	exit( 1 );
}

// Replace everything between `var ABILITIES = [` and the closing `];`.
// A callback is used so the generated body is inserted literally (never
// reinterpreted as regex backreferences or replacement metacharacters).
$pattern  = '/(var\s+ABILITIES\s*=\s*\[)(.*?)(\]\s*;)/s';
$count    = 0;
$new_html = preg_replace_callback(
	$pattern,
	function ( $m ) use ( $array_body ) {
		return $m[1] . $array_body . '];';
	},
	$html,
	1,
	$count
);

if ( null === $new_html || 0 === $count ) {
	fwrite( STDERR, "Error: could not locate `var ABILITIES = [ ... ];` in {$html_path}\n" );
	exit( 1 );
}

if ( $new_html !== $html && false === file_put_contents( $html_path, $new_html ) ) {
	fwrite( STDERR, "Error: could not write {$html_path}\n" );
	exit( 1 );
}
fwrite( STDERR, "Patched ABILITIES array (" . count( $entries ) . " entries) in {$html_path}\n" );

// --- Patch sitemap.xml lastmod (best effort, non-fatal) ---------------------
if ( '' !== $sitemap_path && is_file( $sitemap_path ) ) {
	$today   = gmdate( 'Y-m-d' );
	$sitemap = file_get_contents( $sitemap_path );
	// Update <lastmod> inside the <url> block whose <loc> mentions abilities-directory.
	$sm_pattern = '/(<loc>[^<]*abilities-directory[^<]*<\/loc>\s*<lastmod>)[^<]*(<\/lastmod>)/s';
	$new_sm     = preg_replace( $sm_pattern, '${1}' . $today . '${2}', $sitemap, 1, $sm_count );
	if ( null !== $new_sm && $sm_count > 0 ) {
		if ( $new_sm !== $sitemap ) {
			file_put_contents( $sitemap_path, $new_sm );
		}
		fwrite( STDERR, "Updated sitemap lastmod to {$today} in {$sitemap_path}\n" );
	} else {
		fwrite( STDERR, "Notice: abilities-directory <url> block not found in sitemap; skipped.\n" );
	}
}

exit( 0 );
