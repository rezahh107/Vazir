<?php
/**
 * Exact-installed Gravity Perks structural probe.
 *
 * Records only class/method signatures, semantic token presence, file-relative
 * provenance and line numbers. Licensed vendor source text is never copied.
 */
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
if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) { throw new RuntimeException( 'Gravity Forms is not active.' ); }
if ( ! is_plugin_active( 'gravityperks/gravityperks.php' ) ) { throw new RuntimeException( 'Gravity Perks is not active.' ); }

$plugin_root = WP_PLUGIN_DIR . '/gravityperks';
$manage_file = $plugin_root . '/admin/manage_perks.php';
if ( ! is_readable( $manage_file ) ) { throw new RuntimeException( 'Exact Gravity Perks manage_perks.php is unavailable.' ); }
if ( ! class_exists( 'GWPerksPage' ) ) {
	require_once $manage_file;
}

/** @return array<string,mixed> */
function vazir_perks_reflect_class( string $class_name, string $plugin_root ): array {
	if ( ! class_exists( $class_name ) ) { return array( 'exists' => false ); }
	$reflection = new ReflectionClass( $class_name );
	$file = $reflection->getFileName();
	$relative = is_string( $file ) && 0 === strpos( $file, $plugin_root ) ? ltrim( substr( $file, strlen( $plugin_root ) ), '/\\' ) : null;
	$methods = array();
	foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED ) as $method ) {
		if ( $method->getDeclaringClass()->getName() !== $class_name ) { continue; }
		$params = array();
		foreach ( $method->getParameters() as $parameter ) {
			$params[] = array( 'name' => $parameter->getName(), 'optional' => $parameter->isOptional(), 'has_default' => $parameter->isDefaultValueAvailable() );
		}
		$methods[ $method->getName() ] = array(
			'visibility' => $method->isPublic() ? 'public' : 'protected',
			'static' => $method->isStatic(),
			'parameters' => $params,
			'start_line' => $method->getStartLine(),
			'end_line' => $method->getEndLine(),
		);
	}
	ksort( $methods );
	$defaults = array();
	foreach ( $reflection->getDefaultProperties() as $key => $value ) {
		if ( ! preg_match( '/(?:slug|version|name|id|min_|documentation|setting|view)/i', (string) $key ) ) { continue; }
		if ( is_scalar( $value ) || null === $value || ( is_array( $value ) && count( $value ) <= 20 ) ) { $defaults[ $key ] = $value; }
	}
	return array(
		'exists' => true,
		'parent' => ( $reflection->getParentClass() instanceof ReflectionClass ) ? $reflection->getParentClass()->getName() : null,
		'abstract' => $reflection->isAbstract(),
		'file' => $relative,
		'methods' => $methods,
		'interesting_defaults' => $defaults,
	);
}

/** @return int|null */
function vazir_perks_line_of( string $source, string $needle ) {
	$position = strpos( $source, $needle );
	if ( false === $position ) { return null; }
	return substr_count( substr( $source, 0, $position ), "\n" ) + 1;
}

/** @return array<string,mixed> */
function vazir_perks_method_semantics( string $class_name, string $method_name, string $plugin_root ): array {
	if ( ! class_exists( $class_name ) || ! method_exists( $class_name, $method_name ) ) { return array( 'available' => false ); }
	$method = new ReflectionMethod( $class_name, $method_name );
	$file = $method->getFileName();
	if ( ! is_string( $file ) || 0 !== strpos( $file, $plugin_root ) || ! is_readable( $file ) ) { return array( 'available' => false ); }
	$lines = file( $file );
	if ( ! is_array( $lines ) ) { return array( 'available' => false ); }
	$body = implode( '', array_slice( $lines, max( 0, $method->getStartLine() - 1 ), max( 0, $method->getEndLine() - $method->getStartLine() + 1 ) ) );
	$tokens = array(
		'method_is_overridden' => 'method_is_overridden',
		'calls_documentation' => 'documentation(',
		'calls_settings' => 'settings(',
		'calls_get_documentation' => 'get_documentation(',
		'calls_get_settings' => 'get_settings(',
		'calls_get_perk_settings' => 'get_perk_settings(',
		'calls_save_perk_settings' => 'save_perk_settings(',
		'calls_load_documentation' => 'load_documentation(',
		'calls_load_perk_settings' => 'load_perk_settings(',
		'uses_markdown' => 'markdown(',
		'uses_wp_remote_get' => 'wp_remote_get',
		'uses_file_get_contents' => 'file_get_contents',
		'reads_get_slug' => '$_GET[\'slug\']',
		'reads_get_perk' => '$_GET[\'perk\']',
		'reads_get_view' => "gwget( 'view' )",
		'reads_rgget_view' => "rgget( 'view' )",
		'reads_post' => '$_POST',
		'literal_documentation' => "'documentation'",
		'literal_perk_settings' => "'perk_settings'",
		'dynamic_load_prefix' => "'load_'",
		'has_call_user_func' => 'call_user_func',
		'has_is_callable' => 'is_callable',
		'has_method_exists' => 'method_exists',
		'prints_wp_styles' => 'wp_print_styles',
		'prints_gwp_admin' => 'gwp-admin',
		'google_fonts' => 'fonts.googleapis.com',
		'has_apply_filters' => 'apply_filters',
		'has_do_action' => 'do_action',
		'has_wp_enqueue_style' => 'wp_enqueue_style',
	);
	$present = array();
	foreach ( $tokens as $key => $needle ) { $present[ $key ] = false !== strpos( $body, $needle ); }
	return array(
		'available' => true,
		'file' => ltrim( substr( $file, strlen( $plugin_root ) ), '/\\' ),
		'start_line' => $method->getStartLine(),
		'end_line' => $method->getEndLine(),
		'tokens' => $present,
	);
}

$manage_source = file_get_contents( $manage_file );
if ( ! is_string( $manage_source ) ) { throw new RuntimeException( 'Could not read Gravity Perks manage_perks.php.' ); }
$patterns = array(
	'load_documentation' => 'function load_documentation',
	'load_perk_settings' => 'function load_perk_settings',
	'google_fonts' => 'fonts.googleapis.com',
	'remove_wp_print_styles' => "remove_all_actions( 'wp_print_styles' )",
	'remove_wp_print_scripts' => "remove_all_actions( 'wp_print_scripts' )",
	'perk_iframe' => 'perk-iframe',
	'gwp_admin' => 'gwp-admin',
	'colors_fresh' => 'colors-fresh',
);
$manage_lines = array();
foreach ( $patterns as $key => $needle ) { $manage_lines[ $key ] = vazir_perks_line_of( $manage_source, $needle ); }

$scan_patterns = array(
	'perk_header' => 'Perk: True',
	'extends_gwperk' => 'extends GWPerk',
	'extends_gp_perk' => 'extends GP_Perk',
	'get_documentation' => 'get_documentation',
	'get_settings' => 'get_settings',
	'perk_settings' => 'perk_settings',
	'documentation_url' => 'documentation_url',
	'load_documentation_ref' => 'load_documentation',
	'load_perk_settings_ref' => 'load_perk_settings',
	'gwget_view' => "gwget( 'view' )",
	'rgget_view' => "rgget( 'view' )",
	'view_documentation_literal' => "'documentation'",
	'view_perk_settings_literal' => "'perk_settings'",
	'dynamic_load_prefix' => "'load_'",
);
$hits = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file_info ) {
	if ( ! $file_info instanceof SplFileInfo || ! $file_info->isFile() || 'php' !== strtolower( $file_info->getExtension() ) ) { continue; }
	$path = $file_info->getPathname(); $source = file_get_contents( $path ); if ( ! is_string( $source ) ) { continue; }
	$relative = ltrim( substr( $path, strlen( $plugin_root ) ), '/\\' );
	foreach ( $scan_patterns as $key => $needle ) {
		$line = vazir_perks_line_of( $source, $needle );
		if ( null !== $line ) { $hits[ $key ][] = array( 'file' => $relative, 'line' => $line ); }
	}
}

$classes = array();
foreach ( array( 'GravityPerks', 'GWPerks', 'GWPerk', 'GP_Perk', 'GWPerksPage' ) as $class_name ) { $classes[ $class_name ] = vazir_perks_reflect_class( $class_name, $plugin_root ); }
$semantics = array();
foreach ( array(
	'GP_Perk' => array( '__construct', 'get_documentation', 'documentation', 'display_documentation', 'get_settings', 'settings', 'perk_settings', 'get_perk_data', 'get_perk', 'get_link_for', 'is_perk' ),
	'GWPerksPage' => array( 'load_page', 'load_documentation', 'load_perk_settings' ),
	'GravityPerks' => array( 'init', 'admin_init', 'init_admin' ),
) as $class_name => $methods ) {
	foreach ( $methods as $method_name ) { $semantics[ $class_name . '::' . $method_name ] = vazir_perks_method_semantics( $class_name, $method_name, $plugin_root ); }
}

$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/gravityperks/gravityperks.php', false, false );
$evidence = array(
	'schema' => 3,
	'evidence_class' => 'GRAVITY_PERKS_2_3_16_EXACT_INSTALLED_STRUCTURAL_PROBE',
	'repository_sha' => getenv( 'VAZIR_LAB_REPOSITORY_SHA' ) ?: null,
	'gravity_forms_version' => class_exists( 'GFForms' ) && isset( GFForms::$version ) ? (string) GFForms::$version : null,
	'gravity_perks' => array( 'name' => (string) ( $plugin_data['Name'] ?? '' ), 'version' => (string) ( $plugin_data['Version'] ?? '' ), 'entrypoint' => 'gravityperks/gravityperks.php' ),
	'classes' => $classes,
	'method_semantics' => $semantics,
	'manage_perks' => array( 'file' => 'admin/manage_perks.php', 'sha256' => hash_file( 'sha256', $manage_file ), 'pattern_lines' => $manage_lines ),
	'package_pattern_hits' => $hits,
	'licensed_source_exported' => false,
);
file_put_contents( $artifact_dir . '/gravityperks-source-probe.json', wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES ) . "\n";
