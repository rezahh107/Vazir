<?php
/** Runtime identity and fixture contract for exact GP Advanced Select qualification. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$expected = array(
	'gravityforms/gravityforms.php' => '3.1.1.1',
	'gravityperks/gravityperks.php' => '2.3.16',
	'gp-advanced-select/gp-advanced-select.php' => '1.1.21',
	'vazir-font-wp/vazir-font-wp.php' => VAZIR_FONT_VERSION,
);
$plugins = get_plugins();
foreach ( $expected as $entrypoint => $version ) {
	if ( ! is_plugin_active( $entrypoint ) || (string) ( $plugins[ $entrypoint ]['Version'] ?? '' ) !== (string) $version ) {
		throw new RuntimeException( 'GP Advanced Select profile plugin identity mismatch: ' . $entrypoint );
	}
}
if ( ! class_exists( 'GP_Advanced_Select' ) || ! defined( 'GP_ADVANCED_SELECT_VERSION' ) || '1.1.21' !== (string) GP_ADVANCED_SELECT_VERSION ) {
	throw new RuntimeException( 'Exact GP Advanced Select runtime is unavailable.' );
}
$manifest = get_option( 'vazir_gp_advanced_select_fixture_manifest' );
if ( ! is_array( $manifest ) || empty( $manifest['form_id'] ) || empty( $manifest['frontend_url'] ) ) {
	throw new RuntimeException( 'GP Advanced Select authentic fixture is unavailable.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
if ( ! is_array( $form ) || count( $form['fields'] ?? array() ) !== 3 ) { throw new RuntimeException( 'GP Advanced Select fixture form is incomplete.' ); }
foreach ( $form['fields'] as $field ) {
	if ( empty( $field['gpadvsEnable'] ) ) { throw new RuntimeException( 'Fixture field is not admitted through the real GP Advanced Select setting.' ); }
}
$results = array(
	'status' => 'PASS',
	'profile' => 'gp-advanced-select',
	'package_version' => '1.1.21',
	'evidence_only_version_identity' => true,
	'production_adapter_added' => false,
	'assertions' => array(
		'exact_plugins_active' => 'PASS',
		'authentic_gravity_forms_fixture' => 'PASS',
		'advanced_select_capability_active' => 'PASS',
	),
);
file_put_contents( $artifact_dir . '/gp-advanced-select-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
