<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$expected = array( 'gravityforms/gravityforms.php' => '3.1.1.1', 'gravityflow/gravityflow.php' => '3.1.0', 'gravityview/gravityview.php' => '3.3.4', 'vazir-font-wp/vazir-font-wp.php' => VAZIR_FONT_VERSION );
$plugins = get_plugins();
foreach ( $expected as $entrypoint => $version ) { if ( ! is_plugin_active( $entrypoint ) || (string) ( $plugins[ $entrypoint ]['Version'] ?? '' ) !== (string) $version ) { throw new RuntimeException( 'Combined stack plugin identity mismatch: ' . $entrypoint ); } }
if ( ! is_array( get_option( 'vazir_gf_evidence_fixture_manifest' ) ) || ! is_array( get_option( 'vazir_flow_evidence_fixture_manifest' ) ) || ! is_array( get_option( 'vazir_view_evidence_fixture_manifest' ) ) ) { throw new RuntimeException( 'Combined stack representative fixtures are incomplete.' ); }
$results = array( 'status' => 'PASS', 'profile' => 'gravity-stack', 'assertions' => array( 'all_exact_plugins_active' => 'PASS', 'representative_fixture_coexistence' => 'PASS' ) );
file_put_contents( $artifact_dir . '/gravity-stack-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
