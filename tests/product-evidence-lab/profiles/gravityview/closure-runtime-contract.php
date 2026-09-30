<?php
/** Exact-source lifecycle evidence for the final GravityView portal/oEmbed closure qualification. */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' );
}
wp_mkdir_p( $artifact_dir );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$plugins = get_plugins();
$assert( is_plugin_active( 'gravityforms/gravityforms.php' ), 'Gravity Forms is not active.' );
$assert( is_plugin_active( 'gravityview/gravityview.php' ), 'GravityView is not active.' );
$assert( '3.1.1.1' === (string) ( $plugins['gravityforms/gravityforms.php']['Version'] ?? '' ), 'Gravity Forms runtime version mismatch.' );
$assert( '3.3.4' === (string) ( $plugins['gravityview/gravityview.php']['Version'] ?? '' ), 'GravityView runtime version mismatch.' );
$assert( '7.1' === get_bloginfo( 'version' ), 'WordPress runtime version mismatch.' );

$manifest = get_option( 'vazir_view_evidence_fixture_manifest' );
$assert( is_array( $manifest ), 'GravityView fixture manifest is missing.' );
$assert( ! empty( $manifest['oembed_editor_page_id'] ), 'GravityView authentic oEmbed insertion fixture is missing.' );

$files = array(
	'gravityview_oembed' => WP_PLUGIN_DIR . '/gravityview/src/Media/oEmbed.php',
	'wp_ajax_actions'    => ABSPATH . 'wp-admin/includes/ajax-actions.php',
	'wp_mce_view'        => ABSPATH . WPINC . '/js/mce-view.js',
	'wp_embed_class'     => ABSPATH . WPINC . '/class-wp-embed.php',
	'wp_embed_bootstrap' => ABSPATH . WPINC . '/embed.php',
);

$sources  = array();
$metadata = array();
foreach ( $files as $key => $absolute_path ) {
	$assert( is_readable( $absolute_path ), 'Required source is unreadable: ' . $key );
	$text = file_get_contents( $absolute_path );
	$assert( is_string( $text ), 'Required source could not be read: ' . $key );
	$sources[ $key ] = $text;
	$metadata[ $key ] = array(
		'path'   => str_replace( ABSPATH, '', $absolute_path ),
		'sha256' => hash_file( 'sha256', $absolute_path ),
	);
}

$line_of = static function ( string $source, string $needle ) {
	$position = strpos( $source, $needle );
	if ( false === $position ) {
		return null;
	}
	return substr_count( substr( $source, 0, $position ), "\n" ) + 1;
};

$extract_literal_calls = static function ( string $source, array $functions ): array {
	$function_pattern = implode( '|', array_map( static fn( string $name ): string => preg_quote( $name, '/' ), $functions ) );
	$pattern = '/\b(?:' . $function_pattern . ')\s*\(\s*([\'\"])([^\'\"]+)\1/';
	if ( ! preg_match_all( $pattern, $source, $matches ) ) {
		return array();
	}
	$values = array_values( array_unique( $matches[2] ) );
	sort( $values, SORT_STRING );
	return $values;
};

$extract_methods = static function ( string $source ): array {
	if ( ! preg_match_all( '/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches ) ) {
		return array();
	}
	$values = array_values( array_unique( $matches[1] ) );
	sort( $values, SORT_STRING );
	return $values;
};

$gravityview_hooks = $extract_literal_calls(
	$sources['gravityview_oembed'],
	array( 'add_action', 'add_filter', 'apply_filters', 'do_action' )
);
$gravityview_methods = $extract_methods( $sources['gravityview_oembed'] );
$gravityview_embed_hooks = array_values(
	array_filter(
		$gravityview_hooks,
		static fn( string $name ): bool => (bool) preg_match( '/(?:embed|oembed|gravityview|gk)/i', $name )
	)
);

$wp_embed_hooks = $extract_literal_calls(
	$sources['wp_embed_class'] . "\n" . $sources['wp_embed_bootstrap'] . "\n" . $sources['wp_ajax_actions'],
	array( 'add_action', 'add_filter', 'apply_filters', 'do_action' )
);
$wp_embed_filter_candidates = array_values(
	array_filter(
		$wp_embed_hooks,
		static fn( string $name ): bool => (bool) preg_match( '/embed|oembed/i', $name )
	)
);

$tokens = array(
	'gravityview_placeholder' => array(
		'present' => false !== strpos( $sources['gravityview_oembed'], '<div class="loading-placeholder"' ),
		'line'    => $line_of( $sources['gravityview_oembed'], '<div class="loading-placeholder"' ),
	),
	'gravityview_heading_inline_font' => array(
		'present' => false !== strpos( $sources['gravityview_oembed'], '<h3 style="margin:0; padding:0; font-family:' ),
		'line'    => $line_of( $sources['gravityview_oembed'], '<h3 style="margin:0; padding:0; font-family:' ),
	),
	'gravityview_paragraph_inline_font' => array(
		'present' => false !== strpos( $sources['gravityview_oembed'], '<p style="margin:0; padding:0; font-family:' ),
		'line'    => $line_of( $sources['gravityview_oembed'], '<p style="margin:0; padding:0; font-family:' ),
	),
	'gravityview_register_handler' => array(
		'present' => false !== strpos( $sources['gravityview_oembed'], 'wp_embed_register_handler' ),
		'line'    => $line_of( $sources['gravityview_oembed'], 'wp_embed_register_handler' ),
	),
	'gravityview_add_provider' => array(
		'present' => false !== strpos( $sources['gravityview_oembed'], 'wp_oembed_add_provider' ),
		'line'    => $line_of( $sources['gravityview_oembed'], 'wp_oembed_add_provider' ),
	),
	'wordpress_parse_embed_handler' => array(
		'present' => false !== strpos( $sources['wp_ajax_actions'], 'function wp_ajax_parse_embed()' ),
		'line'    => $line_of( $sources['wp_ajax_actions'], 'function wp_ajax_parse_embed()' ),
	),
	'wordpress_mce_parse_embed_action' => array(
		'present' => 1 === preg_match( '/action\s*:\s*[\'\"]parse-embed[\'\"]/', $sources['wp_mce_view'] ),
	),
	'wordpress_mce_embed_registration' => array(
		'present' => 1 === preg_match( '/(?:views\.)?register\s*\(\s*[\'\"]embed[\'\"]/', $sources['wp_mce_view'] ),
	),
	'wordpress_mce_embed_url_registration' => array(
		'present' => 1 === preg_match( '/(?:views\.)?register\s*\(\s*[\'\"]embedURL[\'\"]/', $sources['wp_mce_view'] ),
	),
	'wordpress_wpview_sandbox' => array(
		'present' => false !== strpos( $sources['wp_mce_view'], 'iframe.wpview-sandbox' ),
		'line'    => $line_of( $sources['wp_mce_view'], 'iframe.wpview-sandbox' ),
	),
);

foreach ( array( 'gravityview_placeholder', 'gravityview_heading_inline_font', 'gravityview_paragraph_inline_font', 'wordpress_parse_embed_handler', 'wordpress_mce_parse_embed_action' ) as $required_token ) {
	$assert( true === $tokens[ $required_token ]['present'], 'Expected exact-runtime source token is missing: ' . $required_token );
}

$results = array(
	'status' => 'PASS',
	'profile' => 'gravityview-closure',
	'runtime' => array(
		'wordpress'    => get_bloginfo( 'version' ),
		'gravityforms' => (string) $plugins['gravityforms/gravityforms.php']['Version'],
		'gravityview'  => (string) $plugins['gravityview/gravityview.php']['Version'],
	),
	'files' => $metadata,
	'tokens' => $tokens,
	'gravityview_oembed' => array(
		'literal_hook_names'                  => $gravityview_hooks,
		'embed_related_literal_hook_names'    => $gravityview_embed_hooks,
		'method_names'                        => $gravityview_methods,
		'licensed_source_exported'             => false,
	),
	'wordpress_embed_lifecycle' => array(
		'embed_related_literal_hook_names' => $wp_embed_filter_candidates,
		'generic_mce_embed_path'           => true === $tokens['wordpress_mce_embed_registration']['present'] || true === $tokens['wordpress_mce_embed_url_registration']['present'],
	),
);

file_put_contents(
	$artifact_dir . '/gravityview-closure-runtime-results.json',
	wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
