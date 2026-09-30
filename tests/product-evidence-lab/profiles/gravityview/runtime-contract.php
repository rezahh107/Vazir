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
$assert( '3.1.1.1' === (string) ( $plugins['gravityforms/gravityforms.php']['Version'] ?? '' ), 'Gravity Forms runtime version mismatch.' );
$assert( '3.3.4' === (string) ( $plugins['gravityview/gravityview.php']['Version'] ?? '' ), 'GravityView runtime version mismatch.' );
$assert( 2 === (int) ( $manifest['schema'] ?? 0 ), 'GravityView fixture schema mismatch.' );
$assert( 'gravityview' === get_post_type( (int) $manifest['view_id'] ), 'Synthetic GravityView post is unavailable.' );
$assert( (int) get_post_meta( (int) $manifest['view_id'], '_gravityview_form_id', true ) === (int) $manifest['form_id'], 'GravityView form binding mismatch.' );
$assert( 'default_table' === get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_template', true ), 'GravityView table template mismatch.' );
$fields = get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_fields', true );
$assert( is_array( $fields ) && ! empty( $fields['directory_table-columns'] ), 'GravityView field configuration is unavailable.' );
$template_settings = get_post_meta( (int) $manifest['view_id'], '_gravityview_template_settings', true );
$assert( is_array( $template_settings ) && 'vantage' === (string) ( $template_settings['theme'] ?? '' ), 'GravityView modern Vantage theme fixture is not explicit.' );
$gutenberg_content = get_post_field( 'post_content', (int) ( $manifest['gutenberg_page_id'] ?? 0 ) );
$parsed_blocks = is_string( $gutenberg_content ) ? parse_blocks( $gutenberg_content ) : array();
$gravityview_blocks = array_values( array_filter( $parsed_blocks, static function ( array $block ): bool { return 'gk-gravityview-blocks/view' === (string) ( $block['blockName'] ?? '' ); } ) );
$assert( count( $gravityview_blocks ) >= 2, 'GravityView Gutenberg fixture must contain configured and unconfigured real View blocks.' );
$registry = WP_Block_Type_Registry::get_instance();
$block_type = $registry->get_registered( 'gk-gravityview-blocks/view' );
$assert( $block_type instanceof WP_Block_Type, 'GravityView View block is not registered.' );
$editor_script_handles = isset( $block_type->editor_script_handles ) && is_array( $block_type->editor_script_handles ) ? $block_type->editor_script_handles : array();
$editor_style_handles  = isset( $block_type->editor_style_handles ) && is_array( $block_type->editor_style_handles ) ? $block_type->editor_style_handles : array();
$style_handles         = isset( $block_type->style_handles ) && is_array( $block_type->style_handles ) ? $block_type->style_handles : array();
$assert( ! empty( $editor_script_handles ), 'GravityView View block editor script handle is unavailable.' );
$assert( ! empty( $editor_style_handles ), 'GravityView View block editor style handle is unavailable.' );
$wp_scripts = wp_scripts();
$wp_styles  = wp_styles();
$describe_handles = static function ( array $handles, $registry_object ): array {
	$described = array();
	foreach ( $handles as $handle ) {
		$handle = (string) $handle;
		$registered = isset( $registry_object->registered[ $handle ] ) ? $registry_object->registered[ $handle ] : null;
		$described[] = array(
			'handle'     => $handle,
			'registered' => null !== $registered,
			'src'        => null !== $registered ? (string) $registered->src : null,
		);
	}
	return $described;
};
$block_assets = array(
	'editor_scripts' => $describe_handles( $editor_script_handles, $wp_scripts ),
	'editor_styles'  => $describe_handles( $editor_style_handles, $wp_styles ),
	'styles'         => $describe_handles( $style_handles, $wp_styles ),
);
foreach ( array_merge( $block_assets['editor_scripts'], $block_assets['editor_styles'] ) as $asset ) {
	$assert( true === $asset['registered'], 'A GravityView View block editor asset handle is not registered.' );
}
$plugin_root = WP_PLUGIN_DIR . '/gravityview';
$line_of = static function ( string $source, string $needle ): ?int {
	$position = strpos( $source, $needle );
	if ( false === $position ) { return null; }
	return substr_count( substr( $source, 0, $position ), "\n" ) + 1;
};
$probe_file = static function ( string $relative, array $tokens ) use ( $plugin_root, $line_of, $assert ): array {
	$absolute = $plugin_root . '/' . ltrim( $relative, '/' );
	$assert( is_readable( $absolute ), 'GravityView source probe file is unreadable: ' . $relative );
	$source = file_get_contents( $absolute );
	$assert( is_string( $source ), 'GravityView source probe could not read: ' . $relative );
	$token_lines = array();
	foreach ( $tokens as $name => $needle ) {
		$line = $line_of( $source, $needle );
		$assert( null !== $line, 'Expected GravityView source token is missing: ' . $relative . ' :: ' . $name );
		$token_lines[ $name ] = $line;
	}
	return array(
		'file'        => $relative,
		'sha256'      => hash_file( 'sha256', $absolute ),
		'size'        => filesize( $absolute ),
		'token_lines' => $token_lines,
	);
};
$source_probe = array(
	'schema'                   => 1,
	'evidence_class'           => 'GRAVITYVIEW_3_3_4_EXACT_INSTALLED_STRUCTURAL_PROBE',
	'repository_sha'           => getenv( 'VAZIR_LAB_REPOSITORY_SHA' ) ?: null,
	'gravity_forms_version'    => (string) ( $plugins['gravityforms/gravityforms.php']['Version'] ?? '' ),
	'gravityview_version'      => (string) ( $plugins['gravityview/gravityview.php']['Version'] ?? '' ),
	'licensed_source_exported' => false,
	'files'                    => array(
		'document_aware_select' => $probe_file(
			'src/PageBuilder/Gutenberg/shared/js/document-aware-select.js',
			array(
				'editor_system_stack'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
				'document_head_cache'  => 'container: doc.head',
				'document_body_portal' => 'menuPortalTarget={ doc.body }',
				'container_style'      => 'container: ( base, state )',
				'menu_portal_style'    => 'menuPortal: ( base, state )',
				'control_suffix_query' => '[class$="-control"]',
			)
		),
		'view_editor_css' => $probe_file(
			'src/PageBuilder/Gutenberg/build/view.css',
			array(
				'react_datepicker_root' => '.react-datepicker{',
				'datepicker_system_font' => 'font-family:Helvetica Neue,helvetica,arial,sans-serif',
			)
		),
		'oembed' => $probe_file(
			'src/Media/oEmbed.php',
			array(
				'render_admin'        => 'private static function render_admin',
				'loading_placeholder' => 'class="loading-placeholder"',
				'inline_system_font'  => 'font-family: -apple-system, BlinkMacSystemFont',
				'ajax_branch'         => '\\GV\\Request::is_ajax()',
				'add_oembed_preview'  => 'is_add_oembed_preview()',
			)
		),
		'token_registry' => $probe_file(
			'src/Settings/TokenRegistry.php',
			array(
				'font_family_token'   => "'css_var' => '--gv-font-family'",
				'font_family_inherit' => "'default' => 'inherit'",
			)
		),
		'view_styles' => $probe_file(
			'src/Settings/ViewStyles.php',
			array(
				'site_wide_override_filter' => 'gk/gravityview/theme/overrides',
				'per_view_override_filter'   => 'gk/gravityview/theme/view/{view_id}/overrides',
			)
		),
		'block_registration' => $probe_file(
			'src/PageBuilder/Gutenberg/Blocks.php',
			array(
				'editor_asset_hook'       => 'enqueue_block_editor_assets',
				'register_style'          => 'wp_register_style(',
				'register_script'         => 'wp_register_script(',
				'register_block_metadata' => 'register_block_type_from_metadata',
			)
		),
	),
	'block_assets' => $block_assets,
);
file_put_contents( $artifact_dir . '/gravityview-source-probe.json', wp_json_encode( $source_probe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
$results = array(
	'status'  => 'PASS',
	'profile' => 'gravityview',
	'assertions' => array(
		'plugins_active'                   => 'PASS',
		'exact_gravityforms_version'       => 'PASS',
		'exact_gravityview_version'        => 'PASS',
		'real_view_post'                   => 'PASS',
		'form_binding'                     => 'PASS',
		'table_configuration'              => 'PASS',
		'explicit_modern_vantage_theme'    => 'PASS',
		'real_gutenberg_view_blocks'       => 'PASS',
		'gutenberg_view_block_registered'  => 'PASS',
		'gutenberg_editor_assets_registered' => 'PASS',
		'exact_source_risk_tokens_present' => 'PASS',
	),
	'block_assets' => $block_assets,
	'oembed_fixture' => ! empty( $manifest['oembed_page_id'] ) && ! empty( $manifest['oembed_entry_url'] ) ? 'READY' : 'NOT_PROVEN',
	'source_risks' => array(
		'modern_frontend_font_token'   => 'SOURCE_PRESENT_DEFAULT_INHERIT',
		'gutenberg_react_select'        => 'SOURCE_PRESENT_SYSTEM_FONT',
		'gutenberg_react_select_portal' => 'SOURCE_PRESENT_SYSTEM_FONT_PORTALED_TO_COMPONENT_DOCUMENT_BODY',
		'gutenberg_datepicker'          => 'SOURCE_PRESENT_SYSTEM_FONT',
		'oembed_admin_placeholder'      => 'SOURCE_PRESENT_INLINE_SYSTEM_FONT',
	),
);
file_put_contents( $artifact_dir . '/gravityview-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
