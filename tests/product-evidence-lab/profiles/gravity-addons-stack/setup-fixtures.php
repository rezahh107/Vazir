<?php
/** Combined same-page fixture for GP Advanced Select + GP File Upload Pro coexistence. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GP_Advanced_Select' ) || ! class_exists( 'GP_File_Upload_Pro' ) ) {
	throw new RuntimeException( 'Combined add-on capabilities are unavailable.' );
}
$form = array(
	'title' => 'Vazir Gravity Add-ons Coexistence',
	'fields' => array(
		array(
			'id' => 1,
			'label' => 'Combined Advanced Select',
			'type' => 'select',
			'gpadvsEnable' => true,
			'placeholder' => 'Choose coexistence value',
			'choices' => array(
				array( 'text' => 'Combined Alpha', 'value' => 'alpha' ),
				array( 'text' => 'Combined Beta', 'value' => 'beta' ),
			),
		),
		array(
			'id' => 2,
			'label' => 'Combined File Upload Pro',
			'type' => 'fileupload',
			'multipleFiles' => true,
			'gpfupEnable' => true,
			'allowedExtensions' => 'png,jpg,jpeg',
		),
	),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) { throw new RuntimeException( $form_id->get_error_message() ); }
$page_id = wp_insert_post(
	array(
		'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Vazir Gravity Add-ons Coexistence',
		'post_name' => 'vazir-gravity-addons-coexistence',
		'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="false"]', (int) $form_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) { throw new RuntimeException( $page_id->get_error_message() ); }
$manifest = array(
	'form_id' => (int) $form_id,
	'page_id' => (int) $page_id,
	'frontend_url' => add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) ),
	'advanced_select_field_id' => 1,
	'file_upload_field_id' => 2,
);
update_option( 'vazir_gravity_addons_stack_fixture_manifest', $manifest, false );
file_put_contents( $artifact_dir . '/gravity-addons-stack-fixture.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES ) . "\n";
