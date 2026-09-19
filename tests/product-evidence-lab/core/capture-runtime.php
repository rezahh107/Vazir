<?php
/** Shared runtime identity capture for licensed Product Evidence Lab profiles. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$profile = getenv( 'VAZIR_LAB_PROFILE' );
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
$repo_sha = getenv( 'VAZIR_LAB_REPOSITORY_SHA' );
if ( ! is_string( $profile ) || '' === $profile || ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $repo_sha ) || '' === $repo_sha ) { throw new RuntimeException( 'VAZIR_LAB_PROFILE, VAZIR_LAB_ARTIFACT_DIR and VAZIR_LAB_REPOSITORY_SHA are required.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$expected = array(
	'gravityforms' => array( 'gravityforms/gravityforms.php' => '3.1.1.1' ),
	'gravityflow' => array( 'gravityforms/gravityforms.php' => '3.1.1.1', 'gravityflow/gravityflow.php' => '3.1.0' ),
	'gravityview' => array( 'gravityforms/gravityforms.php' => '3.1.1.1', 'gravityview/gravityview.php' => '3.3.4' ),
	'gravity-stack' => array( 'gravityforms/gravityforms.php' => '3.1.1.1', 'gravityflow/gravityflow.php' => '3.1.0', 'gravityview/gravityview.php' => '3.3.4' ),
);
if ( ! isset( $expected[ $profile ] ) ) { throw new RuntimeException( 'Unsupported licensed Product Evidence Lab profile: ' . $profile ); }
$plugins = get_plugins(); $observed = array();
foreach ( $expected[ $profile ] as $entrypoint => $version ) {
	if ( ! isset( $plugins[ $entrypoint ] ) || ! is_plugin_active( $entrypoint ) ) { throw new RuntimeException( 'Expected active plugin is unavailable: ' . $entrypoint ); }
	$actual_version = (string) ( $plugins[ $entrypoint ]['Version'] ?? '' );
	if ( $actual_version !== $version ) { throw new RuntimeException( sprintf( '%s expected version %s, got %s.', $entrypoint, $version, $actual_version ) ); }
	$observed[ $entrypoint ] = array( 'name' => (string) ( $plugins[ $entrypoint ]['Name'] ?? '' ), 'version' => $actual_version, 'active' => true );
}
if ( ! defined( 'VAZIR_FONT_VERSION' ) || ! is_plugin_active( 'vazir-font-wp/vazir-font-wp.php' ) ) { throw new RuntimeException( 'Exact Vazir plugin under test is not active.' ); }
$identity = array( 'status' => 'PASS', 'profile' => $profile, 'repository' => getenv( 'GITHUB_REPOSITORY' ) ?: 'rezahh107/Vazir', 'repository_sha' => $repo_sha, 'wordpress_version' => get_bloginfo( 'version' ), 'php_version' => PHP_VERSION, 'vazir_version' => (string) VAZIR_FONT_VERSION, 'theme' => wp_get_theme()->get_stylesheet(), 'plugins' => $observed );
file_put_contents( $artifact_dir . '/runtime-identity.json', wp_json_encode( $identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $identity, JSON_UNESCAPED_SLASHES ) . "\n";
