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
$assert( 'gravityview' === get_post_type( (int) $manifest['view_id'] ), 'Synthetic GravityView post is unavailable.' );
$assert( (int) get_post_meta( (int) $manifest['view_id'], '_gravityview_form_id', true ) === (int) $manifest['form_id'], 'GravityView form binding mismatch.' );
$assert( 'default_table' === get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_template', true ), 'GravityView table template mismatch.' );
$settings = get_post_meta( (int) $manifest['view_id'], '_gravityview_template_settings', true );
$assert( is_array( $settings ) && 'vantage' === (string) ( $settings['theme'] ?? '' ), 'GravityView fixture is not explicitly bound to the modern Vantage theme.' );
$fields = get_post_meta( (int) $manifest['view_id'], '_gravityview_directory_fields', true );
$assert( is_array( $fields ) && ! empty( $fields['directory_table-columns'] ), 'GravityView field configuration is unavailable.' );
$assert( ! empty( $manifest['block_post_id'] ) && 'page' === get_post_type( (int) $manifest['block_post_id'] ), 'GravityView Gutenberg fixture post is unavailable.' );
$blocks = parse_blocks( (string) get_post_field( 'post_content', (int) $manifest['block_post_id'] ) );
$assert( ! empty( $blocks[0]['blockName'] ) && 'gk-gravityview-blocks/view' === $blocks[0]['blockName'], 'GravityView View block serialization mismatch.' );
$assert( (string) ( $blocks[0]['attrs']['viewId'] ?? '' ) === (string) $manifest['view_id'], 'GravityView View block fixture is not bound to the qualified View.' );
if ( ! empty( $manifest['oembed_entry_url'] ) ) {
	$embed_blocks = array_values( array_filter( $blocks, static function ( array $block ): bool { return 'core/embed' === ( $block['blockName'] ?? '' ); } ) );
	$assert( ! empty( $embed_blocks ), 'Authentic WordPress core/embed fixture is unavailable for the GravityView entry URL.' );
	$normalize_url = static function ( string $url ): array {
		$url   = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) { return array(); }
		$query = array();
		if ( ! empty( $parts['query'] ) ) { parse_str( (string) $parts['query'], $query ); ksort( $query ); }
		return array(
			'scheme' => (string) ( $parts['scheme'] ?? '' ),
			'host'   => (string) ( $parts['host'] ?? '' ),
			'port'   => isset( $parts['port'] ) ? (int) $parts['port'] : null,
			'path'   => rtrim( (string) ( $parts['path'] ?? '' ), '/' ),
			'query'  => $query,
		);
	};
	$serialized_embed_url = (string) ( $embed_blocks[0]['attrs']['url'] ?? '' );
	$assert( '' !== $serialized_embed_url, 'GravityView oEmbed fixture block has no URL.' );
	$assert( $normalize_url( $serialized_embed_url ) === $normalize_url( (string) $manifest['oembed_entry_url'] ), 'GravityView oEmbed fixture URL semantics mismatch.' );
}
$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'gk-gravityview-blocks/view' );
$assert( $block_type instanceof WP_Block_Type, 'GravityView View block is not registered.' );
$assert( ! empty( $block_type->editor_script_handles ), 'GravityView View block has no registered editor script handle.' );
$assert( ! empty( $block_type->editor_style_handles ), 'GravityView View block has no registered editor style handle.' );

$plugin_root = WP_PLUGIN_DIR . '/gravityview';
$line_of = static function ( string $source, string $needle ) {
	$position = strpos( $source, $needle );
	if ( false === $position ) { return null; }
	return substr_count( substr( $source, 0, $position ), "\n" ) + 1;
};
$probe_file = static function ( string $relative, array $patterns ) use ( $plugin_root, $line_of ): array {
	$path = $plugin_root . '/' . ltrim( $relative, '/\\' );
	if ( ! is_readable( $path ) ) { throw new RuntimeException( 'Required GravityView source probe is unavailable: ' . $relative ); }
	$source = file_get_contents( $path );
	if ( ! is_string( $source ) ) { throw new RuntimeException( 'Could not read GravityView source probe: ' . $relative ); }
	$lines = array();
	foreach ( $patterns as $key => $needle ) { $lines[ $key ] = $line_of( $source, $needle ); }
	return array( 'file' => $relative, 'sha256' => hash_file( 'sha256', $path ), 'pattern_lines' => $lines );
};
$source_evidence = array(
	'schema' => 1,
	'evidence_class' => 'GRAVITYVIEW_3_3_4_EXACT_INSTALLED_TYPOGRAPHY_STRUCTURAL_PROBE',
	'probes' => array(
		'modern_theme_css' => $probe_file( 'templates/css/gv-theme.css', array( 'font_token_inherit' => '--gv-font-family: inherit', 'font_token_application' => 'font-family:var(--gv-font-family)', 'themed_container_marker' => '.gv-themed' ) ),
		'token_registry' => $probe_file( 'src/Settings/TokenRegistry.php', array( 'font_family_token' => 'font_family', 'font_css_var' => '--gv-font-family', 'font_inherit' => "'inherit'" ) ),
		'view_styles' => $probe_file( 'src/Settings/ViewStyles.php', array( 'global_override_filter' => 'gk/gravityview/theme/overrides', 'view_override_filter' => 'gk/gravityview/theme/view/' ) ),
		'document_aware_select' => $probe_file( 'src/PageBuilder/Gutenberg/shared/js/document-aware-select.js', array( 'editor_font_object' => 'editorFont', 'font_family' => 'fontFamily', 'emotion_key' => 'gk-select', 'portal_target_body' => 'menuPortalTarget={ doc.body }', 'container_style' => 'container:', 'menu_portal_style' => 'menuPortal:' ) ),
		'view_editor_css' => $probe_file( 'src/PageBuilder/Gutenberg/build/view.css', array( 'datepicker_root' => '.react-datepicker', 'datepicker_family' => 'font-family:Helvetica Neue,helvetica,arial,sans-serif' ) ),
		'gutenberg_blocks' => $probe_file( 'src/PageBuilder/Gutenberg/Blocks.php', array( 'editor_assets_hook' => 'enqueue_block_editor_assets', 'asset_handle' => 'generate_block_asset_handle', 'editor_style' => 'editor_style', 'register_style' => 'wp_register_style' ) ),
		'oembed' => $probe_file( 'src/Media/oEmbed.php', array( 'embed_handler' => 'wp_embed_register_handler', 'pre_oembed_filter' => 'pre_oembed_result', 'admin_renderer' => 'render_admin', 'loading_placeholder' => 'loading-placeholder', 'inline_font_family' => 'font-family:', 'ajax_branch' => 'is_ajax()', 'embed_preview_branch' => 'is_add_oembed_preview()' ) ),
	),
	'view_block_registration' => array(
		'name' => 'gk-gravityview-blocks/view',
		'editor_script_handles' => array_values( (array) $block_type->editor_script_handles ),
		'editor_style_handles' => array_values( (array) $block_type->editor_style_handles ),
		'style_handles' => array_values( (array) $block_type->style_handles ),
	),
	'licensed_source_exported' => false,
);
foreach ( $source_evidence['probes'] as $probe ) {
	foreach ( $probe['pattern_lines'] as $semantic => $line ) {
		$assert( is_int( $line ) && $line > 0, sprintf( 'GravityView source semantic token is missing: %s in %s', $semantic, $probe['file'] ) );
	}
}
file_put_contents( $artifact_dir . '/gravityview-source-probe.json', wp_json_encode( $source_evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

$results = array(
	'status'     => 'PASS',
	'profile'    => 'gravityview',
	'assertions' => array(
		'plugins_active'              => 'PASS',
		'exact_gravity_forms_version' => 'PASS',
		'exact_gravityview_version'   => 'PASS',
		'real_view_post'              => 'PASS',
		'form_binding'                => 'PASS',
		'table_configuration'         => 'PASS',
		'modern_vantage_theme'        => 'PASS',
		'real_view_block_fixture'     => 'PASS',
		'view_block_registered'       => 'PASS',
	),
	'source_probe' => 'PASS',
	'block_registration' => array(
		'name'                  => 'gk-gravityview-blocks/view',
		'editor_script_handles' => array_values( (array) $block_type->editor_script_handles ),
		'editor_style_handles'  => array_values( (array) $block_type->editor_style_handles ),
		'style_handles'         => array_values( (array) $block_type->style_handles ),
	),
);
file_put_contents( $artifact_dir . '/gravityview-runtime-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $results, JSON_UNESCAPED_SLASHES ) . "\n";
