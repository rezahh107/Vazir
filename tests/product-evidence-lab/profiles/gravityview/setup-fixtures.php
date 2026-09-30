<?php
/** Deterministic GravityView profile fixture built from the installed product schema. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
wp_mkdir_p( $artifact_dir );
if ( ! class_exists( 'GFAPI' ) ) { throw new RuntimeException( 'Gravity Forms API is unavailable.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'gravityview/gravityview.php' ) ) { throw new RuntimeException( 'GravityView is not active.' ); }

$create_block_fixture = static function ( int $view_id, string $oembed_entry_url = '' ): array {
	$block = array(
		'blockName'    => 'gk-gravityview-blocks/view',
		'attrs'        => array( 'viewId' => (string) $view_id, 'previewBlock' => false ),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
	$content = serialize_block( $block );
	if ( '' !== $oembed_entry_url ) {
		$embed_block = array(
			'blockName'    => 'core/embed',
			'attrs'        => array( 'url' => $oembed_entry_url, 'type' => 'rich', 'providerNameSlug' => 'gravityview', 'responsive' => true ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		$content .= "\n\n" . serialize_block( $embed_block );
	}
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Vazir GravityView Gutenberg Evidence',
			'post_name'    => 'vazir-gravityview-gutenberg-evidence',
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) { throw new RuntimeException( $post_id->get_error_message() ); }
	return array(
		'block_post_id'    => (int) $post_id,
		'block_name'       => 'gk-gravityview-blocks/view',
		'block_editor_url' => admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
	);
};

$existing = get_option( 'vazir_view_evidence_fixture_manifest' );
if ( is_array( $existing ) && ! empty( $existing['view_id'] ) && 'gravityview' === get_post_type( (int) $existing['view_id'] ) ) {
	$settings = get_post_meta( (int) $existing['view_id'], '_gravityview_template_settings', true );
	$settings = is_array( $settings ) ? $settings : array();
	$settings['theme'] = 'vantage';
	update_post_meta( (int) $existing['view_id'], '_gravityview_template_settings', $settings );
	if ( empty( $existing['block_post_id'] ) || ! get_post( (int) $existing['block_post_id'] ) ) {
		$existing = array_merge( $existing, $create_block_fixture( (int) $existing['view_id'] ) );
	}
	update_option( 'vazir_view_evidence_fixture_manifest', $existing, false );
	file_put_contents( $artifact_dir . '/gravityview-fixture.json', wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	return;
}

$options = VazirFontPlugin::get_options();
$exclude = isset( $options['exclude_selectors'] ) && is_array( $options['exclude_selectors'] ) ? $options['exclude_selectors'] : array();
if ( ! in_array( '.vf-view-excluded', $exclude, true ) ) { $exclude[] = '.vf-view-excluded'; }
VazirFontPlugin::update_options( array( 'enable_frontend' => true, 'enable_admin' => true, 'enable_gravity_forms' => true, 'exclude_selectors' => $exclude ) );

$form_id = GFAPI::add_form(
	array(
		'title'          => 'Vazir GravityView Evidence',
		'markupVersion'  => 2,
		'labelPlacement' => 'top_label',
		'fields'         => array(
			array( 'id' => 1, 'label' => 'نام', 'type' => 'text' ),
			array( 'id' => 2, 'label' => 'گروه', 'type' => 'text' ),
		),
		'button'         => array( 'type' => 'text', 'text' => 'ارسال' ),
	)
);
if ( is_wp_error( $form_id ) ) { throw new RuntimeException( $form_id->get_error_message() ); }
$form_id = (int) $form_id;
$entry_ids = array();
foreach ( array( array( 'آلفا', 'گروه یک' ), array( 'بتا', 'گروه دو' ), array( 'گاما', 'گروه سه' ) ) as $row ) {
	$id = GFAPI::add_entry( array( 'form_id' => $form_id, '1' => $row[0], '2' => $row[1] ) );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$entry_ids[] = (int) $id;
}

$view_id = wp_insert_post( array( 'post_type' => 'gravityview', 'post_status' => 'publish', 'post_title' => 'Vazir Evidence View' ), true );
if ( is_wp_error( $view_id ) ) { throw new RuntimeException( $view_id->get_error_message() ); }
$view_id = (int) $view_id;
update_post_meta( $view_id, '_gravityview_form_id', $form_id );
update_post_meta( $view_id, '_gravityview_directory_template', 'default_table' );
update_post_meta( $view_id, '_gravityview_single_template', 'default_table' );
update_post_meta( $view_id, '_gravityview_template_settings', array( 'page_size' => '2', 'show_only_approved' => '0', 'hide_empty' => '0', 'sort_field' => '', 'sort_direction' => 'ASC', 'theme' => 'vantage' ) );
update_post_meta( $view_id, '_gravityview_directory_fields', array( 'directory_table-columns' => array( 'vazir-name' => array( 'id' => '1', 'label' => 'نام', 'show_label' => '1', 'custom_label' => '', 'custom_class' => '', 'only_loggedin' => '0', 'only_loggedin_cap' => 'read', 'show_as_link' => '0', 'search_filter' => '1' ), 'vazir-group' => array( 'id' => '2', 'label' => 'گروه', 'show_label' => '1', 'custom_label' => '', 'custom_class' => '', 'only_loggedin' => '0', 'only_loggedin_cap' => 'read', 'show_as_link' => '0', 'search_filter' => '1' ) ) ) );
update_post_meta( $view_id, '_gravityview_directory_widgets', array( 'header_top' => array( 'vazir-search' => array( 'id' => 'search_bar', 'label' => 'Search Bar', 'search_fields' => '[{"field":"search_all","input":"input_text"}]', 'search_layout' => 'horizontal', 'search_clear' => '1' ) ), 'header_right' => array( 'vazir-pages' => array( 'id' => 'page_links', 'label' => 'Page Links', 'show_all' => '0' ) ), 'footer_right' => array( 'vazir-pages-footer' => array( 'id' => 'page_links', 'label' => 'Page Links', 'show_all' => '0' ) ) ) );

$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Vazir GravityView Evidence', 'post_name' => 'vazir-gravityview-evidence', 'post_content' => '<div class="vf-view-excluded" id="vf-view-excluded" style="font-family: monospace;">View excluded typography</div>' . sprintf( '[gravityview id="%d"]', $view_id ) ), true );
if ( is_wp_error( $page_id ) ) { throw new RuntimeException( $page_id->get_error_message() ); }

$oembed_entry_url = '';
if ( class_exists( '\\GV\\GF_Entry' ) && class_exists( '\\GV\\View' ) && ! empty( $entry_ids[0] ) ) {
	$entry = \GV\GF_Entry::by_id( $entry_ids[0] );
	$view  = \GV\View::by_id( $view_id );
	if ( $entry instanceof \GV\Entry && $view instanceof \GV\View ) {
		$oembed_entry_url = (string) $entry->get_permalink( $view );
	}
}
$block_fixture = $create_block_fixture( $view_id, $oembed_entry_url );

$manifest = array_merge(
	array(
		'form_id'          => $form_id,
		'view_id'          => $view_id,
		'entry_ids'        => $entry_ids,
		'page_id'          => (int) $page_id,
		'frontend_url'     => get_permalink( (int) $page_id ),
		'admin_view_url'   => admin_url( 'post.php?post=' . $view_id . '&action=edit' ),
		'exclude_selector' => '.vf-view-excluded',
		'oembed_entry_url' => $oembed_entry_url,
	),
	$block_fixture
);
update_option( 'vazir_view_evidence_fixture_manifest', $manifest, false );
file_put_contents( $artifact_dir . '/gravityview-fixture.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES ) . "\n";
