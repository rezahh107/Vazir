<?php
/** Native exact-runtime assertions for Gravity Perks qualification. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
$manifest_path = $artifact_dir . '/fixture-manifest.json';
if ( ! is_readable( $manifest_path ) ) { throw new RuntimeException( 'Gravity Perks fixture manifest is unavailable.' ); }
$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
if ( ! is_array( $manifest ) ) { throw new RuntimeException( 'Gravity Perks fixture manifest is invalid.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$assert = static function ( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } };

$assert( is_plugin_active( 'gravityforms/gravityforms.php' ), 'Gravity Forms prerequisite is not active.' );
$assert( is_plugin_active( 'gravityperks/gravityperks.php' ), 'Gravity Perks is not active.' );
$assert( is_plugin_active( 'vazir-font-wp/vazir-font-wp.php' ), 'Vazir under test is not active.' );
$assert( is_plugin_active( (string) $manifest['fixture_plugin'] ), 'Real test-only Perk fixture is not active.' );
$assert( class_exists( 'GP_Perk' ) && class_exists( 'GWPerk' ), 'Gravity Perks Perk API is unavailable.' );
$assert( '3.1.1.1' === (string) GFForms::$version, 'Unexpected Gravity Forms runtime version.' );
$gp_data = get_plugin_data( WP_PLUGIN_DIR . '/gravityperks/gravityperks.php', false, false );
$assert( '2.3.16' === (string) ( $gp_data['Version'] ?? '' ), 'Unexpected Gravity Perks runtime version.' );
$assert( true === (bool) $manifest['fixture_is_perk'], 'Fixture is not recognized by Gravity Perks as a Perk.' );
$assert( 'True' === (string) $manifest['fixture_perk_header'], 'Fixture Perk header is not exact.' );
$perk = GP_Perk::get_perk( (string) $manifest['fixture_plugin'] );
$assert( $perk instanceof GP_Perk, 'Fixture did not instantiate through the real GP_Perk API.' );
$assert( 'GP_Vazir_Evidence' === get_class( $perk ), 'Unexpected fixture Perk class.' );
$assert( (string) $manifest['perk_basename'] === (string) $manifest['fixture_plugin'], 'Perk basename does not match the real plugin file.' );
$assert( false !== strpos( (string) $manifest['documentation_url'], 'page=gwp_perks' ), 'Documentation URL is not owned by the Gravity Perks admin route.' );
$assert( false !== strpos( (string) $manifest['documentation_url'], 'view=documentation' ), 'Documentation URL is not the real standalone Documentation view.' );
$assert( false !== strpos( (string) $manifest['settings_url'], 'page=gwp_perks' ), 'Settings URL is not owned by the Gravity Perks admin route.' );
$assert( false !== strpos( (string) $manifest['settings_url'], 'view=perk_settings' ), 'Settings URL is not the real standalone Settings view.' );
$assert( array( '.vazir-gp-evidence-excluded' ) === $manifest['exclude_selectors'], 'Existing exclusion authority was not preserved in the fixture.' );

$manage_file = WP_PLUGIN_DIR . '/gravityperks/admin/manage_perks.php';
$source = file_get_contents( $manage_file );
$assert( is_string( $source ), 'Exact Gravity Perks manage_perks.php is unreadable.' );
$source_checks = array(
	'load_documentation' => false !== strpos( $source, 'function load_documentation' ),
	'load_perk_settings' => false !== strpos( $source, 'function load_perk_settings' ),
	'literal_google_fonts' => false !== strpos( $source, 'fonts.googleapis.com' ),
	'remove_wp_print_styles' => false !== strpos( $source, "remove_all_actions( 'wp_print_styles' )" ),
	'remove_wp_print_scripts' => false !== strpos( $source, "remove_all_actions( 'wp_print_scripts' )" ),
	'prints_gwp_admin' => false !== strpos( $source, 'gwp-admin' ),
	'perk_iframe' => false !== strpos( $source, 'perk-iframe' ),
);
foreach ( $source_checks as $name => $value ) { $assert( $value, 'Expected exact-source capability missing: ' . $name ); }

$method_evidence = array();
foreach ( array( 'load_documentation', 'load_perk_settings' ) as $method_name ) {
	if ( ! class_exists( 'GWPerksPage' ) ) { require_once $manage_file; }
	$method = new ReflectionMethod( 'GWPerksPage', $method_name );
	$lines = file( $method->getFileName() );
	$body = is_array( $lines ) ? implode( '', array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) ) : '';
	$method_evidence[ $method_name ] = array(
		'file' => 'admin/manage_perks.php',
		'start_line' => $method->getStartLine(),
		'end_line' => $method->getEndLine(),
		'google_fonts_literal' => false !== strpos( $body, 'fonts.googleapis.com' ),
		'wp_print_styles' => false !== strpos( $body, 'wp_print_styles' ),
		'apply_filters' => false !== strpos( $body, 'apply_filters' ),
		'do_action' => false !== strpos( $body, 'do_action' ),
		'wp_enqueue_style' => false !== strpos( $body, 'wp_enqueue_style' ),
		'remove_wp_print_styles' => false !== strpos( $body, "remove_all_actions( 'wp_print_styles' )" ),
	);
}
$evidence = array(
	'status' => 'PASS',
	'profile' => 'gravityperks',
	'gravity_forms_version' => (string) GFForms::$version,
	'gravity_perks_version' => (string) ( $gp_data['Version'] ?? '' ),
	'fixture' => array(
		'plugin' => $manifest['fixture_plugin'],
		'class' => get_class( $perk ),
		'perk_header' => $manifest['fixture_perk_header'],
		'documentation_url' => $manifest['documentation_url'],
		'settings_url' => $manifest['settings_url'],
		'shipped_in_production' => false,
	),
	'exact_source_checks' => $source_checks,
	'method_evidence' => $method_evidence,
);
file_put_contents( $artifact_dir . '/runtime-contract.json', wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES ) . "\n";
