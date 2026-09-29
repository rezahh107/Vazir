<?php
/**
 * Exact-installed Gravity Perks structural probe.
 *
 * Records only class/method signatures, file-relative provenance and line numbers.
 * Licensed vendor source text is never copied into evidence.
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
if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
	throw new RuntimeException( 'Gravity Forms is not active.' );
}
if ( ! is_plugin_active( 'gravityperks/gravityperks.php' ) ) {
	throw new RuntimeException( 'Gravity Perks is not active.' );
}

$plugin_root = WP_PLUGIN_DIR . '/gravityperks';
$manage_file = $plugin_root . '/admin/manage_perks.php';
if ( ! is_readable( $manage_file ) ) {
	throw new RuntimeException( 'Exact Gravity Perks manage_perks.php is unavailable.' );
}

/** @return array<string,mixed> */
function vazir_perks_reflect_class( string $class_name, string $plugin_root ): array {
	if ( ! class_exists( $class_name ) ) {
		return array( 'exists' => false );
	}
	$reflection = new ReflectionClass( $class_name );
	$file = $reflection->getFileName();
	$relative = is_string( $file ) && 0 === strpos( $file, $plugin_root ) ? ltrim( substr( $file, strlen( $plugin_root ) ), '/\\' ) : null;
	$methods = array();
	foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED ) as $method ) {
		if ( $method->getDeclaringClass()->getName() !== $class_name ) {
			continue;
		}
		$params = array();
		foreach ( $method->getParameters() as $parameter ) {
			$params[] = array(
				'name' => $parameter->getName(),
				'optional' => $parameter->isOptional(),
				'has_default' => $parameter->isDefaultValueAvailable(),
			);
		}
		$methods[ $method->getName() ] = array(
			'visibility' => $method->isPublic() ? 'public' : 'protected',
			'static' => $method->isStatic(),
			'parameters' => $params,
		);
	}
	ksort( $methods );
	return array(
		'exists' => true,
		'parent' => ( $reflection->getParentClass() instanceof ReflectionClass ) ? $reflection->getParentClass()->getName() : null,
		'abstract' => $reflection->isAbstract(),
		'file' => $relative,
		'methods' => $methods,
	);
}

/** @return int|null */
function vazir_perks_line_of( string $source, string $needle ) {
	$position = strpos( $source, $needle );
	if ( false === $position ) {
		return null;
	}
	return substr_count( substr( $source, 0, $position ), "\n" ) + 1;
}

$manage_source = file_get_contents( $manage_file );
if ( ! is_string( $manage_source ) ) {
	throw new RuntimeException( 'Could not read Gravity Perks manage_perks.php.' );
}

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
foreach ( $patterns as $key => $needle ) {
	$manage_lines[ $key ] = vazir_perks_line_of( $manage_source, $needle );
}

$scan_patterns = array(
	'perk_header' => 'Perk: True',
	'extends_gwperk' => 'extends GWPerk',
	'extends_gp_perk' => 'extends GP_Perk',
	'get_documentation' => 'get_documentation',
	'get_settings' => 'get_settings',
	'perk_settings' => 'perk_settings',
	'documentation_url' => 'documentation_url',
);
$hits = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file_info ) {
	if ( ! $file_info instanceof SplFileInfo || ! $file_info->isFile() || 'php' !== strtolower( $file_info->getExtension() ) ) {
		continue;
	}
	$path = $file_info->getPathname();
	$source = file_get_contents( $path );
	if ( ! is_string( $source ) ) {
		continue;
	}
	$relative = ltrim( substr( $path, strlen( $plugin_root ) ), '/\\' );
	foreach ( $scan_patterns as $key => $needle ) {
		$line = vazir_perks_line_of( $source, $needle );
		if ( null !== $line ) {
			$hits[ $key ][] = array( 'file' => $relative, 'line' => $line );
		}
	}
}

$classes = array();
foreach ( array( 'GWPerk', 'GP_Perk', 'GWPerksPage', 'GWPerks' ) as $class_name ) {
	$classes[ $class_name ] = vazir_perks_reflect_class( $class_name, $plugin_root );
}

$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/gravityperks/gravityperks.php', false, false );
$evidence = array(
	'schema' => 1,
	'evidence_class' => 'GRAVITY_PERKS_2_3_16_EXACT_INSTALLED_STRUCTURAL_PROBE',
	'repository_sha' => getenv( 'VAZIR_LAB_REPOSITORY_SHA' ) ?: null,
	'gravity_forms_version' => class_exists( 'GFForms' ) && isset( GFForms::$version ) ? (string) GFForms::$version : null,
	'gravity_perks' => array(
		'name' => (string) ( $plugin_data['Name'] ?? '' ),
		'version' => (string) ( $plugin_data['Version'] ?? '' ),
		'entrypoint' => 'gravityperks/gravityperks.php',
	),
	'classes' => $classes,
	'manage_perks' => array(
		'file' => 'admin/manage_perks.php',
		'sha256' => hash_file( 'sha256', $manage_file ),
		'pattern_lines' => $manage_lines,
	),
	'package_pattern_hits' => $hits,
	'licensed_source_exported' => false,
);

file_put_contents(
	$artifact_dir . '/gravityperks-source-probe.json',
	wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES ) . "\n";
