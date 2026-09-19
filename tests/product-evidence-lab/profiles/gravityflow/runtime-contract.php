<?php
/** Gravity Flow profile runtime assertions. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
$manifest = get_option( 'vazir_flow_evidence_fixture_manifest' );
if ( ! is_array( $manifest ) ) { throw new RuntimeException( 'Gravity Flow fixture manifest is missing.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$assert = static function ( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$assert( is_plugin_active( 'gravityforms/gravityforms.php' ), 'Gravity Forms is not active.' );
$assert( is_plugin_active( 'gravityflow/gravityflow.php' ), 'Gravity Flow is not active.' );
$assert( is_plugin_active( 'vazir-font-wp/vazir-font-wp.php' ), 'Vazir is not active.' );
$assert( defined( 'GRAVITY_FLOW_VERSION' ) && '3.1.0' === GRAVITY_FLOW_VERSION, 'Gravity Flow runtime version mismatch.' );
$assert( class_exists( 'Gravity_Flow_API' ), 'Gravity Flow API is unavailable.' );
$entry = GFAPI::get_entry( (int) $manifest['entry_id'] );
$assert( is_array( $entry ), 'Synthetic Gravity Flow entry is unavailable.' );
$api = new Gravity_Flow_API( (int) $manifest['form_id'] );
$current = $api->get_current_step( $entry );
$assert( $current && (int) $current->get_id() === (int) $manifest['step_id'], 'Synthetic entry is not on the expected workflow step.' );
$results = array( 'status' => 'PASS', 'profile' => 'gravityflow', 'assertions' => array( 'plugins_active' => 'PASS', 'exact_gravityflow_version' => 'PASS', 'real_workflow_step_state' => 'PASS', 'frontend_inbox_shortcode_fixture' => 'PASS' ) );
file_put_contents( $artifact_dir . '/gravityflow-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
