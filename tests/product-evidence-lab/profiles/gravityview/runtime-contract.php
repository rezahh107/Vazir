<?php
/** Exact-runtime GravityView 3.3.4 qualification contract and bounded source evidence. */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' );
}
wp_mkdir_p( $artifact_dir );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$manifest = get_option( 'vazir_view_evidence_fixture_manifest' );
if ( ! is_array( $manifest ) ) {
	throw new RuntimeException( 'GravityView fixture manifest is missing.' );
}

$plugins = get_plugins();
$assert  = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( is_plugin_active( 'gravityforms/gravityforms.php' ), 'Gravity Forms is not active.' );
$assert( is_plugin_active( 'gravityview/gravityview.php' ), 'GravityView is not active.' );
$assert( '3.1.1.1' === (string) ( $plugins['gravityforms/gravityforms.php']['Version'] ?? '' ), 'Gravity Forms runtime version mismatch.' );
$assert( '3.3.4' === (string) ( $plugins['gravityview/gravityview.php']['Version'] ?? '' ), 'GravityView runtime version mismatch.' );
$assert( 3 === (int) ( $manifest['schema'] ?? 0 ), 'GravityView fixture schema mismatch.' );
$assert( 'gravityview' === get_post_type( (int) $manifest['view_id'] ), 'Synthetic GravityView post is unavailable.' );
$assert( 'page' === get_post_type( (int) $manifest['editor_page_id'] ), 'GravityView Gutenberg fixture page is unavailable.' );
$assert( (int) get_post_meta( (int) $manifest['view_id'], '_gravityview_form_id', true ) === (int) $manifest['form_id'], 'GravityView form binding mismatch.' );
$assert( 'default_table' === get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_template', true ), 'GravityView table template mismatch.' );
$assert( class_exists( 'VazirFont_GravityView_Integration' ), 'Vazir GravityView production adapter is unavailable on the qualified runtime.' );

$settings = get_post_meta( (int) $manifest['view_id'], '_gravityview_template_settings', true );
$assert( is_array( $settings ), 'GravityView template settings are unavailable.' );
$assert( 'vantage' === (string) ( $settings['theme'] ?? '' ), 'GravityView evidence View must opt into the Vantage theme.' );
$assert( '2' === (string) ( $settings['page_size'] ?? '' ), 'GravityView evidence View page size mismatch.' );

$fields = get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_fields', true );
$assert( is_array( $fields ) && ! empty( $fields['directory_table-columns'] ), 'GravityView field configuration is unavailable.' );

$editor_post = get_post( (int) $manifest['editor_page_id'] );
$assert( $editor_post instanceof WP_Post, 'GravityView Gutenberg fixture post cannot be loaded.' );
$blocks = parse_blocks( (string) $editor_post->post_content );
$assert( 1 === count( $blocks ), 'GravityView Gutenberg fixture must contain exactly one top-level block.' );
$assert( (string) $manifest['block_name'] === (string) ( $blocks[0]['blockName'] ?? '' ), 'GravityView Gutenberg fixture block name mismatch.' );
$assert( (string) $manifest['view_id'] === (string) ( $blocks[0]['attrs']['viewId'] ?? '' ), 'GravityView Gutenberg fixture View binding mismatch.' );

$registry   = WP_Block_Type_Registry::get_instance();
$block_type = $registry->get_registered( (string) $manifest['block_name'] );
$assert( $block_type instanceof WP_Block_Type, 'GravityView View block is not registered in the exact runtime.' );

$plugin_root = WP_PLUGIN_DIR . '/gravityview';
$source_files = array(
	'document_aware_select' => 'src/PageBuilder/Gutenberg/shared/js/document-aware-select.js',
	'view_editor_css'       => 'src/PageBuilder/Gutenberg/build/view.css',
	'oembed'                => 'src/Media/oEmbed.php',
	'theme_tokens'          => 'templates/css/source/theme/_tokens.scss',
	'token_registry'        => 'src/Settings/TokenRegistry.php',
	'view_styles'           => 'src/Settings/ViewStyles.php',
	'blocks'                => 'src/PageBuilder/Gutenberg/Blocks.php',
);

/**
 * Return 1-based line number of a source token, or null when absent.
 *
 * @param string $source Source text.
 * @param string $needle Token to locate.
 * @return int|null
 */
function vazir_gravityview_line_of( string $source, string $needle ) {
	$position = strpos( $source, $needle );
	if ( false === $position ) {
		return null;
	}
	return substr_count( substr( $source, 0, $position ), "\n" ) + 1;
}

$source_evidence = array(
	'licensed_source_exported' => false,
	'files'                    => array(),
	'tokens'                   => array(),
);
$source_text = array();
foreach ( $source_files as $key => $relative_path ) {
	$absolute_path = $plugin_root . '/' . $relative_path;
	$assert( is_readable( $absolute_path ), 'GravityView source evidence file is unavailable: ' . $relative_path );
	$text = file_get_contents( $absolute_path );
	$assert( is_string( $text ), 'GravityView source evidence file cannot be read: ' . $relative_path );
	$source_text[ $key ] = $text;
	$source_evidence['files'][ $key ] = array(
		'path'   => $relative_path,
		'sha256' => hash_file( 'sha256', $absolute_path ),
	);
}

$token_specs = array(
	'react_select_editor_font'             => array( 'document_aware_select', 'fontFamily: \'-apple-system, BlinkMacSystemFont' ),
	'react_select_control_semantic_suffix' => array( 'document_aware_select', "'[class$=\"-control\"]'" ),
	'react_select_portal_body'             => array( 'document_aware_select', 'menuPortalTarget={ doc.body }' ),
	'react_select_container_slot'          => array( 'document_aware_select', 'container: ( base, state ) =>' ),
	'react_select_menu_portal_slot'        => array( 'document_aware_select', 'menuPortal: ( base, state ) =>' ),
	'datepicker_direct_font'                => array( 'view_editor_css', 'font-family:Helvetica Neue,helvetica,arial,sans-serif' ),
	'oembed_placeholder'                    => array( 'oembed', '<div class="loading-placeholder"' ),
	'oembed_heading_inline_font'            => array( 'oembed', '<h3 style="margin:0; padding:0; font-family:' ),
	'oembed_paragraph_inline_font'          => array( 'oembed', '<p style="margin:0; padding:0; font-family:' ),
	'theme_font_inherit'                    => array( 'theme_tokens', '--gv-font-family: inherit;' ),
	'token_registry_font_family'            => array( 'token_registry', "'css_var' => '--gv-font-family'" ),
	'token_registry_inherit_default'        => array( 'token_registry', "'default' => 'inherit'" ),
	'theme_overrides_filter'                => array( 'view_styles', "apply_filters( 'gk/gravityview/theme/overrides'" ),
	'per_view_overrides_filter'             => array( 'view_styles', 'gk/gravityview/theme/view/{$view_id}/overrides' ),
	'block_editor_enqueue'                  => array( 'blocks', "'enqueue_block_editor_assets'" ),
);
foreach ( $token_specs as $key => $spec ) {
	list( $source_key, $needle ) = $spec;
	$line = vazir_gravityview_line_of( $source_text[ $source_key ], $needle );
	$source_evidence['tokens'][ $key ] = array(
		'present' => null !== $line,
		'file'    => $source_files[ $source_key ],
		'line'    => $line,
	);
}

foreach ( array( 'react_select_editor_font', 'react_select_control_semantic_suffix', 'react_select_portal_body', 'datepicker_direct_font', 'oembed_placeholder', 'theme_font_inherit', 'token_registry_font_family', 'theme_overrides_filter', 'per_view_overrides_filter', 'block_editor_enqueue' ) as $required_source_token ) {
	$assert( true === $source_evidence['tokens'][ $required_source_token ]['present'], 'Expected GravityView source token is missing: ' . $required_source_token );
}

$editor_script_handle = generate_block_asset_handle( (string) $manifest['block_name'], 'editorScript' );
$editor_style_handle  = generate_block_asset_handle( (string) $manifest['block_name'], 'editorStyle' );
$global_style_handle  = generate_block_asset_handle( (string) $manifest['block_name'], 'style' );
$assert( 'gk-gravityview-blocks-view-editor-style' === $editor_style_handle, 'GravityView View editor-style handle identity drifted.' );
$assert( wp_script_is( $editor_script_handle, 'registered' ), 'GravityView View editor script handle is not registered by the host.' );
$assert( wp_style_is( $editor_style_handle, 'registered' ), 'GravityView View editor style handle is not registered by the host.' );
$assert( wp_style_is( $global_style_handle, 'registered' ), 'GravityView View global style handle is not registered by the host.' );
$assert( in_array( $editor_style_handle, (array) $block_type->editor_style_handles, true ), 'GravityView View block does not attach its registered editor style to block metadata.' );

$block_assets = array(
	'editor_script_handles'            => array( $editor_script_handle ),
	'editor_style_handles'             => array( $editor_style_handle ),
	'registered_editor_script_handle'  => $editor_script_handle,
	'registered_editor_style_handle'   => $editor_style_handle,
	'registered_global_style_handle'   => $global_style_handle,
	'block_editor_script_handles'      => array_values( (array) $block_type->editor_script_handles ),
	'block_editor_style_handles'       => array_values( (array) $block_type->editor_style_handles ),
	'block_style_handles'              => array_values( (array) $block_type->style_handles ),
	'host_ownership'                   => 'GravityView registers the editor script/style directly; only editorStyle is also attached to block metadata for iframe propagation.',
	'vazir_production_seam'            => 'enqueue_block_editor_assets -> wp_add_inline_style(gk-gravityview-blocks-view-editor-style)',
);

$results = array(
	'status'     => 'PASS',
	'profile'    => 'gravityview',
	'assertions' => array(
		'plugins_active'             => 'PASS',
		'exact_gravityforms_version' => 'PASS',
		'exact_gravityview_version'  => 'PASS',
		'production_adapter_loaded'  => 'PASS',
		'real_view_post'             => 'PASS',
		'form_binding'               => 'PASS',
		'table_configuration'        => 'PASS',
		'vantage_theme_opt_in'       => 'PASS',
		'real_gutenberg_fixture'     => 'PASS',
		'real_gravityview_block'     => 'PASS',
		'block_asset_registration'   => 'PASS',
		'bounded_source_probe'       => 'PASS',
	),
	'block_assets'    => $block_assets,
	'source_evidence' => $source_evidence,
);

file_put_contents(
	$artifact_dir . '/gravityview-runtime-results.json',
	wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
