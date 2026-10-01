<?php
/** Runtime identity and fixture contract for exact GP File Upload Pro qualification. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$expected = array(
	'gravityforms/gravityforms.php' => '3.1.1.1',
	'gravityperks/gravityperks.php' => '2.3.16',
	'gp-file-upload-pro/gp-file-upload-pro.php' => '1.5.13',
	'vazir-font-wp/vazir-font-wp.php' => VAZIR_FONT_VERSION,
);
$plugins = get_plugins();
foreach ( $expected as $entrypoint => $version ) {
	if ( ! is_plugin_active( $entrypoint ) || (string) ( $plugins[ $entrypoint ]['Version'] ?? '' ) !== (string) $version ) {
		throw new RuntimeException( 'GP File Upload Pro profile plugin identity mismatch: ' . $entrypoint );
	}
}
if ( ! class_exists( 'GP_File_Upload_Pro' ) || ! defined( 'GPFUP_VERSION' ) || '1.5.13' !== (string) GPFUP_VERSION ) {
	throw new RuntimeException( 'Exact GP File Upload Pro runtime is unavailable.' );
}
$manifest = get_option( 'vazir_gp_file_upload_pro_fixture_manifest' );
if ( ! is_array( $manifest ) || empty( $manifest['form_id'] ) || empty( $manifest['frontend_url'] ) ) {
	throw new RuntimeException( 'GP File Upload Pro authentic fixture is unavailable.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
$field = is_array( $form ) && isset( $form['fields'][0] ) ? $form['fields'][0] : null;
if ( ! $field || empty( $field['gpfupEnable'] ) || empty( $field['gpfupEnableCrop'] ) || empty( $field['multipleFiles'] ) ) {
	throw new RuntimeException( 'Fixture is not admitted through the real File Upload Pro + crop settings.' );
}
$results = array(
	'status' => 'PASS',
	'profile' => 'gp-file-upload-pro',
	'package_version' => '1.5.13',
	'evidence_only_version_identity' => true,
	'production_adapter_added' => false,
	'assertions' => array(
		'exact_plugins_active' => 'PASS',
		'authentic_gravity_forms_fixture' => 'PASS',
		'file_upload_pro_capability_active' => 'PASS',
		'crop_capability_configured' => 'PASS',
	),
);
file_put_contents( $artifact_dir . '/gp-file-upload-pro-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
