<?php
/** Exact GP Advanced Select browser fixture. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GP_Advanced_Select' ) ) { throw new RuntimeException( 'Gravity Forms / GP Advanced Select runtime APIs are unavailable.' ); }

$form = array(
	'title' => 'Vazir GP Advanced Select Evidence',
	'fields' => array(
		array(
			'id' => 1,
			'label' => 'Searchable choice',
			'type' => 'select',
			'gpadvsEnable' => true,
			'placeholder' => 'Choose a value',
			'choices' => array(
				array( 'text' => 'Alpha ID-123', 'value' => 'alpha' ),
				array( 'text' => 'Beta user@example.invalid', 'value' => 'beta' ),
				array( 'text' => 'گزینه فارسی', 'value' => 'fa' ),
			),
		),
		array(
			'id' => 2,
			'label' => 'Initially selected choice',
			'type' => 'select',
			'gpadvsEnable' => true,
			'choices' => array(
				array( 'text' => 'Initial selected ID-456', 'value' => 'initial', 'isSelected' => true ),
				array( 'text' => 'Second choice', 'value' => 'second' ),
			),
		),
		array(
			'id' => 3,
			'label' => 'Multiple choices',
			'type' => 'multiselect',
			'gpadvsEnable' => true,
			'choices' => array(
				array( 'text' => 'Multi Alpha', 'value' => 'multi-alpha' ),
				array( 'text' => 'Multi Beta', 'value' => 'multi-beta' ),
				array( 'text' => 'چندگزینه‌ای فارسی', 'value' => 'multi-fa' ),
			),
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
		'post_title' => 'Vazir GP Advanced Select Evidence',
		'post_name' => 'vazir-gp-advanced-select-evidence',
		'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="false"]', (int) $form_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) { throw new RuntimeException( $page_id->get_error_message() ); }

$manifest = array(
	'form_id' => (int) $form_id,
	'page_id' => (int) $page_id,
	'frontend_url' => add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) ),
	'search_field_id' => 1,
	'initial_field_id' => 2,
	'multiselect_field_id' => 3,
	'gravity_forms_version' => (string) GFForms::$version,
	'gp_advanced_select_version' => defined( 'GP_ADVANCED_SELECT_VERSION' ) ? (string) GP_ADVANCED_SELECT_VERSION : '',
);
update_option( 'vazir_gp_advanced_select_fixture_manifest', $manifest, false );
file_put_contents( $artifact_dir . '/gp-advanced-select-fixture.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
