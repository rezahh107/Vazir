<?php
/**
 * Native WordPress/Gravity Forms identity and integration assertions.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'VAZIR_GF_ARTIFACT_DIR' );
$repo_sha     = getenv( 'VAZIR_GF_REPOSITORY_SHA' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $repo_sha ) || '' === $repo_sha ) {
	throw new RuntimeException( 'VAZIR_GF_ARTIFACT_DIR and VAZIR_GF_REPOSITORY_SHA are required.' );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$manifest = get_option( 'vazir_gf_evidence_fixture_manifest' );
if ( ! is_array( $manifest ) ) {
	throw new RuntimeException( 'Gravity Forms evidence fixture manifest is missing.' );
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( class_exists( 'GFAPI' ) && class_exists( 'GFCommon' ), 'Gravity Forms runtime classes are unavailable.' );
$assert( '3.1.1.1' === (string) GFCommon::get_version(), 'Gravity Forms runtime version is not 3.1.1.1.' );
$assert( defined( 'VAZIR_FONT_VERSION' ), 'Vazir runtime version constant is unavailable.' );
$assert( is_plugin_active( 'gravityforms/gravityforms.php' ), 'Gravity Forms is not active.' );
$assert( is_plugin_active( 'vazir-font-wp/vazir-font-wp.php' ), 'Vazir plugin under test is not active.' );
$assert( true === (bool) get_option( 'gform_enable_noconflict' ), 'Gravity Forms No Conflict Mode is not enabled.' );

$orbital = GFAPI::get_form( (int) $manifest['orbital_form_id'] );
$dynamic = GFAPI::get_form( (int) $manifest['dynamic_form_id'] );
$legacy  = GFAPI::get_form( (int) $manifest['legacy_form_id'] );
$assert( is_array( $orbital ) && is_array( $dynamic ) && is_array( $legacy ), 'Synthetic Gravity Forms fixtures are unavailable.' );
$assert( 2 === (int) rgar( $orbital, 'markupVersion' ), 'Orbital fixture markupVersion must be 2.' );
$assert( 2 === (int) rgar( $dynamic, 'markupVersion' ), 'Dynamic fixture markupVersion must be 2.' );
$assert( 1 === (int) rgar( $legacy, 'markupVersion' ), 'Legacy fixture markupVersion must be 1.' );
$assert( ! GFCommon::is_legacy_markup_enabled( $orbital ), 'Orbital fixture unexpectedly resolves to Legacy Markup.' );
$assert( ! GFCommon::is_legacy_markup_enabled( $dynamic ), 'Dynamic fixture unexpectedly resolves to Legacy Markup.' );
$assert( GFCommon::is_legacy_markup_enabled( $legacy ), 'Legacy fixture does not resolve to Legacy Markup.' );

$integration = VazirFont_GravityForms_Integration::get_instance();
$preview     = apply_filters( 'gform_preview_styles', array(), $dynamic );
$noconflict  = apply_filters( 'gform_noconflict_styles', array() );
$assert( in_array( 'vazir-font-gravity-forms', $preview, true ), 'Preview style handle is not registered through gform_preview_styles.' );
$assert( in_array( 'vazir-font-gravity-forms', $noconflict, true ), 'Gravity Forms style handle is not allowlisted in No Conflict Mode.' );
$assert( in_array( 'vazir-font-admin-runtime', $noconflict, true ), 'Vazir admin style handle is not allowlisted in No Conflict Mode.' );
$assert( has_filter( 'gform_field_content', array( $integration, 'remove_inline_font_styles' ) ) !== false, 'gform_field_content compatibility hook was removed.' );
$assert( has_filter( 'gform_field_css_class', array( $integration, 'add_field_css_class' ) ) !== false, 'gform_field_css_class compatibility hook was removed.' );

$identity = array(
	'status'                => 'PASS',
	'repository'            => getenv( 'GITHUB_REPOSITORY' ) ?: 'rezahh107/Vazir',
	'repository_sha'        => $repo_sha,
	'wordpress_version'     => get_bloginfo( 'version' ),
	'php_version'           => PHP_VERSION,
	'gravity_forms_version' => (string) GFCommon::get_version(),
	'vazir_version'         => (string) VAZIR_FONT_VERSION,
	'theme'                 => wp_get_theme()->get_stylesheet(),
	'no_conflict_mode'      => (bool) get_option( 'gform_enable_noconflict' ),
	'plugins'               => array(
		'gravityforms' => is_plugin_active( 'gravityforms/gravityforms.php' ),
		'vazir'        => is_plugin_active( 'vazir-font-wp/vazir-font-wp.php' ),
	),
	'host_assertions'       => array(
		'orbital_markup_runtime' => 'PASS',
		'dynamic_markup_runtime' => 'PASS',
		'legacy_markup_runtime'  => 'PASS',
		'preview_handle'         => 'PASS',
		'noconflict_handles'     => 'PASS',
		'compatibility_hooks'    => 'PASS',
	),
);

file_put_contents(
	$artifact_dir . '/runtime-identity.json',
	wp_json_encode( $identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
file_put_contents(
	$artifact_dir . '/php-results.json',
	wp_json_encode( array( 'status' => 'PASS', 'assertions' => $identity['host_assertions'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
);

echo wp_json_encode( $identity, JSON_UNESCAPED_SLASHES ) . "\n";
