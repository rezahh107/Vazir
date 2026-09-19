<?php
/**
 * Deterministic synthetic fixtures for the licensed Gravity Forms evidence lab.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'VAZIR_GF_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	fwrite( STDERR, "VAZIR_GF_ARTIFACT_DIR is required.\n" );
	exit( 1 );
}
wp_mkdir_p( $artifact_dir );

if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GFCommon' ) ) {
	throw new RuntimeException( 'Licensed Gravity Forms runtime API is unavailable.' );
}

if ( '3.1.1.1' !== (string) GFCommon::get_version() ) {
	throw new RuntimeException( 'Fixture setup requires Gravity Forms 3.1.1.1.' );
}

$options = VazirFontPlugin::get_options();
$exclude = isset( $options['exclude_selectors'] ) && is_array( $options['exclude_selectors'] ) ? $options['exclude_selectors'] : array();
if ( ! in_array( '.vf-gf-excluded', $exclude, true ) ) {
	$exclude[] = '.vf-gf-excluded';
}
VazirFontPlugin::update_options(
	array(
		'enable_frontend'      => true,
		'enable_admin'         => true,
		'enable_gravity_forms' => true,
		'exclude_selectors'    => $exclude,
	)
);
update_option( 'gform_enable_noconflict', true );
update_option( 'rg_gforms_default_theme', 'orbital' );

$existing = get_option( 'vazir_gf_evidence_fixture_manifest' );
if (
	is_array( $existing )
	&& ! empty( $existing['orbital_form_id'] )
	&& ! empty( $existing['dynamic_form_id'] )
	&& ! empty( $existing['legacy_form_id'] )
) {
	file_put_contents(
		$artifact_dir . '/fixture-manifest.json',
		wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
	);
	echo "Gravity Forms evidence fixtures already exist.\n";
	return;
}

/**
 * @param array<string,mixed> $form Form definition accepted by GFAPI.
 */
function vazir_gf_lab_add_form( array $form ): int {
	$form_id = GFAPI::add_form( $form );
	if ( is_wp_error( $form_id ) ) {
		throw new RuntimeException( $form_id->get_error_message() );
	}
	return (int) $form_id;
}

/**
 * @return int Published page ID.
 */
function vazir_gf_lab_add_page( string $title, string $slug, string $shortcode ): int {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $shortcode,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( $post_id->get_error_message() );
	}
	return (int) $post_id;
}

// A plain Orbital fixture isolates the Theme Framework custom-property path.
// It intentionally has no excluded subtree or icon-bearing field because those
// negative-applicability boundaries correctly suppress inheritable ancestor rules.
$orbital_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Orbital Framework',
		'description'    => 'Synthetic Theme Framework typography fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 2,
		'fields'         => array(
			array(
				'id'          => 1,
				'label'       => 'نام',
				'description' => 'توضیح فیلد متنی Mixed Latin 123',
				'type'        => 'text',
			),
			array(
				'id'          => 2,
				'label'       => 'توضیحات',
				'description' => 'توضیح textarea',
				'type'        => 'textarea',
			),
			array(
				'id'      => 3,
				'label'   => 'انتخاب',
				'type'    => 'select',
				'choices' => array(
					array( 'text' => 'گزینه یک', 'value' => 'one' ),
					array( 'text' => 'گزینه دو', 'value' => 'two' ),
				),
			),
		),
		'button' => array( 'type' => 'text', 'text' => 'ارسال آزمایشی' ),
	)
);

// A separate current-markup fixture exercises exclusions, icon protection and
// real dynamic/rerender paths without conflating them with the framework-root rule.
$dynamic_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Dynamic',
		'description'    => 'Synthetic AJAX, validation, multi-page and conditional-logic fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 2,
		'fields'         => array(
			array(
				'id'          => 1,
				'label'       => 'نام الزامی',
				'description' => 'فیلد لازم برای validation rerender',
				'type'        => 'text',
				'isRequired'  => true,
			),
			array(
				'id'      => 2,
				'label'   => 'کنترل شرطی',
				'type'    => 'radio',
				'choices' => array(
					array( 'text' => 'نمایش بده', 'value' => 'show' ),
					array( 'text' => 'پنهان بمان', 'value' => 'hide' ),
				),
			),
			array(
				'id'               => 3,
				'label'            => 'فیلد شرطی',
				'type'             => 'text',
				'conditionalLogic' => array(
					'actionType' => 'show',
					'logicType'  => 'all',
					'rules'      => array(
						array(
							'fieldId'  => 2,
							'operator' => 'is',
							'value'    => 'show',
						),
					),
				),
			),
			array(
				'id'      => 4,
				'label'   => 'Excluded typography fixture',
				'type'    => 'html',
				'content' => '<div class="vf-gf-excluded" id="vf-gf-excluded-root" style="font-family: monospace;"><span id="vf-gf-excluded-text" style="font-family: monospace;">Excluded Gravity Forms typography</span></div>',
			),
			array(
				'id'                        => 5,
				'label'                     => 'رمز عبور',
				'type'                      => 'password',
				'passwordVisibilityEnabled' => true,
			),
			array(
				'id'             => 6,
				'label'          => 'Page Break',
				'type'           => 'page',
				'nextButton'     => array( 'type' => 'text', 'text' => 'بعدی' ),
				'previousButton' => array( 'type' => 'text', 'text' => 'قبلی' ),
			),
			array(
				'id'          => 7,
				'label'       => 'صفحه دوم',
				'description' => 'کنترل بعد از AJAX rerender',
				'type'        => 'text',
				'isRequired'  => true,
			),
		),
		'button' => array( 'type' => 'text', 'text' => 'ارسال نهایی' ),
	)
);

$legacy_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Legacy',
		'description'    => 'Synthetic supported Legacy Markup typography fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 1,
		'fields'         => array(
			array(
				'id'          => 1,
				'label'       => 'Legacy text',
				'description' => 'Legacy description',
				'type'        => 'text',
			),
			array(
				'id'      => 2,
				'label'   => 'Legacy select',
				'type'    => 'select',
				'choices' => array(
					array( 'text' => 'Legacy option', 'value' => 'legacy' ),
				),
			),
		),
		'button' => array( 'type' => 'text', 'text' => 'Legacy submit' ),
	)
);

$orbital_post_id = vazir_gf_lab_add_page(
	'Vazir GF Orbital Framework Evidence',
	'vazir-gf-orbital-evidence',
	sprintf( '[gravityform id="%d" title="true" description="true" ajax="false" theme="orbital"]', $orbital_form_id )
);
$dynamic_post_id = vazir_gf_lab_add_page(
	'Vazir GF Dynamic Evidence',
	'vazir-gf-dynamic-evidence',
	sprintf( '[gravityform id="%d" title="true" description="true" ajax="true" theme="orbital"]', $dynamic_form_id )
);
$legacy_post_id = vazir_gf_lab_add_page(
	'Vazir GF Legacy Evidence',
	'vazir-gf-legacy-evidence',
	sprintf( '[gravityform id="%d" title="true" description="true" ajax="false" theme="legacy"]', $legacy_form_id )
);

$manifest = array(
	'gravity_forms_version'  => (string) GFCommon::get_version(),
	'orbital_form_id'        => $orbital_form_id,
	'dynamic_form_id'        => $dynamic_form_id,
	'legacy_form_id'         => $legacy_form_id,
	'orbital_post_id'        => $orbital_post_id,
	'dynamic_post_id'        => $dynamic_post_id,
	'legacy_post_id'         => $legacy_post_id,
	'orbital_url'            => get_permalink( $orbital_post_id ),
	'dynamic_url'            => get_permalink( $dynamic_post_id ),
	'legacy_url'             => get_permalink( $legacy_post_id ),
	'preview_url'            => add_query_arg(
		array(
			'gf_page' => 'preview',
			'id'      => $dynamic_form_id,
		),
		home_url( '/' )
	),
	'form_editor_url'        => admin_url( 'admin.php?page=gf_edit_forms&id=' . $dynamic_form_id ),
	'no_conflict_mode'       => (bool) get_option( 'gform_enable_noconflict' ),
	'orbital_markup_version' => 2,
	'dynamic_markup_version' => 2,
	'legacy_markup_version'  => 1,
	'exclude_selector'       => '.vf-gf-excluded',
);

update_option( 'vazir_gf_evidence_fixture_manifest', $manifest, false );
file_put_contents(
	$artifact_dir . '/fixture-manifest.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
