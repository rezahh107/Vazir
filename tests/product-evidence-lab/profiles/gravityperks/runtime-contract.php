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
$assert( class_exists( 'GP_Perk' ) && class_exists( 'GWPerk' ) && class_exists( 'GravityPerks' ), 'Gravity Perks runtime API is unavailable.' );
$assert( '3.1.1.1' === (string) GFForms::$version, 'Unexpected Gravity Forms runtime version.' );
$gp_data = get_plugin_data( WP_PLUGIN_DIR . '/gravityperks/gravityperks.php', false, false );
$assert( '2.3.16' === (string) ( $gp_data['Version'] ?? '' ), 'Unexpected Gravity Perks runtime version.' );
$assert( true === (bool) $manifest['fixture_is_perk'], 'Fixture is not recognized by Gravity Perks as a Perk.' );
$assert( 'True' === (string) $manifest['fixture_perk_header'], 'Fixture Perk header is not exact.' );

$perk = GP_Perk::get_perk( (string) $manifest['fixture_plugin'] );
$assert( $perk instanceof GP_Perk, 'Fixture did not instantiate through the real GP_Perk API.' );
$assert( 'GP_Vazir_Evidence' === get_class( $perk ), 'Unexpected fixture Perk class.' );
$assert( (string) $manifest['perk_basename'] === (string) $manifest['fixture_plugin'], 'Perk basename does not match the real plugin file.' );

$documentation_method = new ReflectionMethod( $perk, 'documentation' );
$assert( 'GP_Vazir_Evidence' === $documentation_method->getDeclaringClass()->getName(), 'Fixture documentation() override is not the active runtime implementation.' );
$raw_documentation = $perk->get_documentation();
$assert( is_string( $raw_documentation ), 'Fixture get_documentation() did not return a string.' );
$assert( false !== strpos( $raw_documentation, 'Vazir Perk documentation paragraph' ), 'Fixture get_documentation() lost the expected marker.' );
ob_start();
$perk->display_documentation();
$rendered_documentation = ob_get_clean();
$assert( is_string( $rendered_documentation ), 'Fixture display_documentation() did not produce capturable output.' );
$assert( false !== strpos( $rendered_documentation, 'Vazir Perk documentation paragraph' ), 'Fixture display_documentation() did not render the expected marker.' );

$route_expectations = array(
	'documentation_url' => 'documentation',
	'settings_url' => 'perk_settings',
);
foreach ( $route_expectations as $manifest_key => $expected_view ) {
	$url = (string) ( $manifest[ $manifest_key ] ?? '' );
	$assert( '' !== $url, $manifest_key . ' is empty.' );
	$assert( false === strpos( $url, '&amp;' ) && false === strpos( $url, '&#' ), $manifest_key . ' must be a raw machine-navigable URL, not an HTML-escaped href.' );
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	$assert( is_string( $query ) && '' !== $query, $manifest_key . ' has no query string.' );
	$params = array();
	parse_str( $query, $params );
	$assert( 'gwp_perks' === (string) ( $params['page'] ?? '' ), $manifest_key . ' is not owned by the Gravity Perks admin route.' );
	$assert( $expected_view === (string) ( $params['view'] ?? '' ), $manifest_key . ' does not target the expected standalone view.' );
	$assert( (string) $manifest['fixture_plugin'] === (string) ( $params['slug'] ?? '' ), $manifest_key . ' does not target the real test Perk basename.' );
}
$assert( array( '.vazir-gp-evidence-excluded' ) === $manifest['exclude_selectors'], 'Existing exclusion authority was not preserved in the fixture.' );

$manage_file = WP_PLUGIN_DIR . '/gravityperks/admin/manage_perks.php';
$manage_source = file_get_contents( $manage_file );
$assert( is_string( $manage_source ), 'Exact Gravity Perks manage_perks.php is unreadable.' );
$source_checks = array(
	'load_documentation' => false !== strpos( $manage_source, 'function load_documentation' ),
	'load_perk_settings' => false !== strpos( $manage_source, 'function load_perk_settings' ),
	'literal_google_fonts' => false !== strpos( $manage_source, 'fonts.googleapis.com' ),
	'remove_wp_print_styles' => false !== strpos( $manage_source, "remove_all_actions( 'wp_print_styles' )" ),
	'remove_wp_print_scripts' => false !== strpos( $manage_source, "remove_all_actions( 'wp_print_scripts' )" ),
	'prints_gwp_admin' => false !== strpos( $manage_source, 'gwp-admin' ),
	'perk_iframe' => false !== strpos( $manage_source, 'perk-iframe' ),
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

$init_method = new ReflectionMethod( 'GravityPerks', 'init' );
$init_lines = file( $init_method->getFileName() );
$init_body = is_array( $init_lines ) ? implode( '', array_slice( $init_lines, $init_method->getStartLine() - 1, $init_method->getEndLine() - $init_method->getStartLine() + 1 ) ) : '';
$dispatch_contract = array(
	'file' => 'gravityperks.php',
	'start_line' => $init_method->getStartLine(),
	'end_line' => $init_method->getEndLine(),
	'reads_view_guard' => 1 === preg_match( '/r(?:g|gw)get\(\s*[\'\"]view[\'\"]\s*\)/', $init_body ),
	'calls_load_perk_settings' => false !== strpos( $init_body, 'GWPerksPage::load_perk_settings' ),
	'calls_load_documentation' => false !== strpos( $init_body, 'GWPerksPage::load_documentation' ),
	'documentation_url_view' => 'documentation',
	'settings_url_view' => 'perk_settings',
);
$assert( true === $dispatch_contract['reads_view_guard'], 'Exact Gravity Perks init() no longer exposes the expected view guard.' );
$assert( true === $dispatch_contract['calls_load_perk_settings'], 'Exact Gravity Perks init() no longer dispatches view requests to load_perk_settings().' );
$assert( false === $dispatch_contract['calls_load_documentation'], 'Exact Gravity Perks init() unexpectedly dispatches to load_documentation(); qualification model must be revisited.' );
$dispatch_contract['documentation_route_reachable'] = false;
$dispatch_contract['documentation_route_disposition'] = 'NOT_REACHABLE_AS_DOCUMENTATION';
$dispatch_contract['view_requests_dispatch_to'] = 'GWPerksPage::load_perk_settings';
file_put_contents( $artifact_dir . '/dispatch-contract.json', wp_json_encode( $dispatch_contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

$evidence = array(
	'status' => 'PASS',
	'profile' => 'gravityperks',
	'gravity_forms_version' => (string) GFForms::$version,
	'gravity_perks_version' => (string) ( $gp_data['Version'] ?? '' ),
	'fixture' => array(
		'plugin' => $manifest['fixture_plugin'],
		'class' => get_class( $perk ),
		'perk_header' => $manifest['fixture_perk_header'],
		'documentation_declaring_class' => $documentation_method->getDeclaringClass()->getName(),
		'raw_documentation_marker_present' => false !== strpos( $raw_documentation, 'Vazir Perk documentation paragraph' ),
		'rendered_documentation_marker_present' => false !== strpos( $rendered_documentation, 'Vazir Perk documentation paragraph' ),
		'raw_documentation_length' => strlen( $raw_documentation ),
		'rendered_documentation_length' => strlen( $rendered_documentation ),
		'documentation_url' => $manifest['documentation_url'],
		'settings_url' => $manifest['settings_url'],
		'shipped_in_production' => false,
	),
	'exact_source_checks' => $source_checks,
	'method_evidence' => $method_evidence,
	'dispatch_contract' => $dispatch_contract,
);
file_put_contents( $artifact_dir . '/runtime-contract.json', wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES ) . "\n";
