<?php
/** Additional exact-runtime fixture for the final GravityView portal/oEmbed closure qualification. */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' );
}

$manifest = get_option( 'vazir_view_evidence_fixture_manifest' );
if ( ! is_array( $manifest ) || 3 !== (int) ( $manifest['schema'] ?? 0 ) ) {
	throw new RuntimeException( 'GravityView base fixture manifest is missing or incompatible.' );
}

$entry_url = (string) ( $manifest['oembed_entry_url'] ?? '' );
if ( '' === $entry_url ) {
	throw new RuntimeException( 'GravityView oEmbed entry URL is unavailable.' );
}

$existing_page_id = (int) ( $manifest['oembed_editor_page_id'] ?? 0 );
if ( $existing_page_id > 0 && 'page' === get_post_type( $existing_page_id ) ) {
	$manifest['oembed_editor_url'] = admin_url( 'post.php?post=' . $existing_page_id . '&action=edit' );
	update_option( 'vazir_view_evidence_fixture_manifest', $manifest, false );
	file_put_contents(
		$artifact_dir . '/gravityview-fixture.json',
		wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
	);
	return;
}

$shortcode = '[embed]' . esc_url_raw( $entry_url ) . '[/embed]';
$block = serialize_block(
	array(
		'blockName'    => 'core/freeform',
		'attrs'        => array(
			'className' => 'vazir-gv-evidence-excluded',
		),
		'innerBlocks'  => array(),
		'innerHTML'    => $shortcode,
		'innerContent' => array( $shortcode ),
	)
);

$oembed_editor_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Vazir GravityView oEmbed Insertion Evidence',
		'post_name'    => 'vazir-gravityview-oembed-insertion-evidence',
		'post_content' => $block,
	),
	true
);
if ( is_wp_error( $oembed_editor_page_id ) ) {
	throw new RuntimeException( $oembed_editor_page_id->get_error_message() );
}

$oembed_editor_page_id = (int) $oembed_editor_page_id;
$manifest['oembed_editor_page_id'] = $oembed_editor_page_id;
$manifest['oembed_editor_url']     = admin_url( 'post.php?post=' . $oembed_editor_page_id . '&action=edit' );
$manifest['oembed_editor_block']   = 'core/freeform';
$manifest['oembed_editor_exclusion_class'] = 'vazir-gv-evidence-excluded';

update_option( 'vazir_view_evidence_fixture_manifest', $manifest, false );
file_put_contents(
	$artifact_dir . '/gravityview-fixture.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES ) . "\n";
