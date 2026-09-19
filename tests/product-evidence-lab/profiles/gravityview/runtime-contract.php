<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$manifest = get_option( 'vazir_view_evidence_fixture_manifest' );
if ( ! is_array( $manifest ) ) { throw new RuntimeException( 'GravityView fixture manifest is missing.' ); }
$plugins = get_plugins();
$assert = static function ( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$assert( is_plugin_active( 'gravityforms/gravityforms.php' ), 'Gravity Forms is not active.' );
$assert( is_plugin_active( 'gravityview/gravityview.php' ), 'GravityView is not active.' );
$assert( '3.3.4' === (string) ( $plugins['gravityview/gravityview.php']['Version'] ?? '' ), 'GravityView runtime version mismatch.' );
$assert( 'gravityview' === get_post_type( (int) $manifest['view_id'] ), 'Synthetic GravityView post is unavailable.' );
$assert( (int) get_post_meta( (int) $manifest['view_id'], '_gravityview_form_id', true ) === (int) $manifest['form_id'], 'GravityView form binding mismatch.' );
$assert( 'default_table' === get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_template', true ), 'GravityView table template mismatch.' );
$fields = get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_fields', true );
$assert( is_array( $fields ) && ! empty( $fields['directory_table-columns'] ), 'GravityView field configuration is unavailable.' );
$results = array( 'status' => 'PASS', 'profile' => 'gravityview', 'assertions' => array( 'plugins_active' => 'PASS', 'exact_gravityview_version' => 'PASS', 'real_view_post' => 'PASS', 'form_binding' => 'PASS', 'table_configuration' => 'PASS' ) );
file_put_contents( $artifact_dir . '/gravityview-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
