<?php
/** Exact GP File Upload Pro browser fixture. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GP_File_Upload_Pro' ) ) { throw new RuntimeException( 'Gravity Forms / GP File Upload Pro runtime APIs are unavailable.' ); }

$form = array(
	'title' => 'Vazir GP File Upload Pro Evidence',
	'fields' => array(
		array(
			'id' => 1,
			'label' => 'Image upload with crop',
			'type' => 'fileupload',
			'multipleFiles' => true,
			'gpfupEnable' => true,
			'gpfupEnableCrop' => true,
			'gpfupCropRequired' => false,
			'allowedExtensions' => 'jpg,jpeg,png',
		),
	),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) { throw new RuntimeException( $form_id->get_error_message() ); }
$page_id = wp_insert_post(
	array(
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_title' => 'Vazir GP File Upload Pro Evidence',
		'post_name' => 'vazir-gp-file-upload-pro-evidence',
		'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="false"]', (int) $form_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) { throw new RuntimeException( $page_id->get_error_message() ); }

$manifest = array(
	'form_id' => (int) $form_id,
	'page_id' => (int) $page_id,
	'frontend_url' => add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) ),
	'file_field_id' => 1,
	'gravity_forms_version' => (string) GFForms::$version,
	'gp_file_upload_pro_version' => defined( 'GPFUP_VERSION' ) ? (string) GPFUP_VERSION : '',
);
update_option( 'vazir_gp_file_upload_pro_fixture_manifest', $manifest, false );
file_put_contents( $artifact_dir . '/gp-file-upload-pro-fixture.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
