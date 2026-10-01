<?php
/** Exact combined-stack runtime contract with both Gravity Perks add-ons enabled. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$expected = array(
	'gravityforms/gravityforms.php' => '3.1.1.1',
	'gravityflow/gravityflow.php' => '3.1.0',
	'gravityview/gravityview.php' => '3.3.4',
	'gravityperks/gravityperks.php' => '2.3.16',
	'gp-advanced-select/gp-advanced-select.php' => '1.1.21',
	'gp-file-upload-pro/gp-file-upload-pro.php' => '1.5.13',
	'vazir-font-wp/vazir-font-wp.php' => VAZIR_FONT_VERSION,
);
$plugins = get_plugins();
foreach ( $expected as $entrypoint => $version ) {
	if ( ! is_plugin_active( $entrypoint ) || (string) ( $plugins[ $entrypoint ]['Version'] ?? '' ) !== (string) $version ) {
		throw new RuntimeException( 'Combined add-on stack plugin identity mismatch: ' . $entrypoint );
	}
}
$required_options = array(
	'vazir_gf_evidence_fixture_manifest',
	'vazir_flow_evidence_fixture_manifest',
	'vazir_view_evidence_fixture_manifest',
	'vazir_gp_advanced_select_fixture_manifest',
	'vazir_gp_file_upload_pro_fixture_manifest',
	'vazir_gravity_addons_stack_fixture_manifest',
);
foreach ( $required_options as $option ) {
	if ( ! is_array( get_option( $option ) ) ) { throw new RuntimeException( 'Combined representative fixture is missing: ' . $option ); }
}

// The retained Gravity Perks runtime/browser evidence owns a generic
// fixture-manifest.json. The combined profile also reuses the Gravity Forms
// profile, which owns the same historical artifact name. Execute the retained
// Perks contract against its saved manifest, then restore the previous owner.
$generic_manifest_path = $artifact_dir . '/fixture-manifest.json';
$perks_manifest_path = $artifact_dir . '/gravityperks-fixture-manifest.json';
if ( ! is_readable( $generic_manifest_path ) || ! is_readable( $perks_manifest_path ) ) {
	throw new RuntimeException( 'Combined profile manifest handoff inputs are unavailable.' );
}
$previous_manifest = (string) file_get_contents( $generic_manifest_path );
$perks_manifest = (string) file_get_contents( $perks_manifest_path );
if ( '' === $previous_manifest || '' === $perks_manifest ) {
	throw new RuntimeException( 'Combined profile manifest handoff inputs are empty.' );
}
try {
	if ( false === file_put_contents( $generic_manifest_path, $perks_manifest ) ) {
		throw new RuntimeException( 'Could not hand the generic fixture manifest to the retained Gravity Perks contract.' );
	}
	require dirname( __DIR__ ) . '/gravityperks/runtime-contract.php';
	require dirname( __DIR__ ) . '/gravityperks/source-probe.php';
} finally {
	if ( false === file_put_contents( $generic_manifest_path, $previous_manifest ) ) {
		throw new RuntimeException( 'Could not restore the generic fixture manifest after retained Gravity Perks qualification.' );
	}
}

$results = array(
	'status' => 'PASS',
	'profile' => 'gravity-addons-stack',
	'assertions' => array(
		'all_exact_plugins_active' => 'PASS',
		'baseline_and_addon_fixtures_coexist' => 'PASS',
		'retained_gravityperks_runtime_contract' => is_readable( $artifact_dir . '/dispatch-contract.json' ) ? 'PASS' : 'FAIL',
		'retained_gravityperks_source_probe' => is_readable( $artifact_dir . '/gravityperks-source-probe.json' ) ? 'PASS' : 'FAIL',
	),
	'evidence_versions' => array(
		'gravity_forms' => '3.1.1.1', 'gravity_flow' => '3.1.0', 'gravityview' => '3.3.4', 'gravity_perks' => '2.3.16',
		'gp_advanced_select' => '1.1.21', 'gp_file_upload_pro' => '1.5.13',
	),
);
foreach ( $results['assertions'] as $name => $status ) {
	if ( 'PASS' !== $status ) { throw new RuntimeException( 'Combined runtime assertion failed: ' . $name ); }
}
file_put_contents( $artifact_dir . '/gravity-addons-stack-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
