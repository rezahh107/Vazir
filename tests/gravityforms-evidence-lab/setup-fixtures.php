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

if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GFCommon' ) || ! class_exists( 'GFForms' ) ) {
	throw new RuntimeException( 'Licensed Gravity Forms runtime API is unavailable.' );
}

if ( '3.1.1.1' !== (string) GFForms::$version ) {
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
	&& ! empty( $existing['legacy_steps_form_id'] )
	&& ! empty( $existing['legacy_percentage_form_id'] )
	&& ! empty( $existing['entries_admin_url'] )
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

/**
 * @param array<string,mixed> $entry Entry accepted by GFAPI.
 */
function vazir_gf_lab_add_entry( array $entry ): int {
	$entry_id = GFAPI::add_entry( $entry );
	if ( is_wp_error( $entry_id ) ) {
		throw new RuntimeException( $entry_id->get_error_message() );
	}
	return (int) $entry_id;
}

$orbital_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Orbital Framework',
		'description'    => 'Synthetic Theme Framework typography fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 2,
		'fields'         => array(
			array( 'id' => 1, 'label' => 'نام', 'description' => 'توضیح فیلد متنی Mixed Latin 123', 'type' => 'text' ),
			array( 'id' => 2, 'label' => 'توضیحات', 'description' => 'توضیح textarea', 'type' => 'textarea' ),
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

$dynamic_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Dynamic',
		'description'    => 'Synthetic AJAX, validation, multi-page and conditional-logic fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 2,
		'fields'         => array(
			array( 'id' => 1, 'label' => 'نام الزامی', 'description' => 'فیلد لازم برای validation rerender', 'type' => 'text', 'isRequired' => true ),
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
						array( 'fieldId' => 2, 'operator' => 'is', 'value' => 'show' ),
					),
				),
			),
			array(
				'id'      => 4,
				'label'   => 'Excluded typography fixture',
				'type'    => 'html',
				'content' => '<div class="vf-gf-excluded" id="vf-gf-excluded-root" style="font-family: monospace;"><span id="vf-gf-excluded-text" style="font-family: monospace;">Excluded Gravity Forms typography</span></div>',
			),
			array( 'id' => 5, 'label' => 'رمز عبور', 'type' => 'password', 'passwordVisibilityEnabled' => true ),
			array(
				'id'             => 6,
				'label'          => 'Page Break',
				'type'           => 'page',
				'nextButton'     => array( 'type' => 'text', 'text' => 'بعدی' ),
				'previousButton' => array( 'type' => 'text', 'text' => 'قبلی' ),
			),
			array( 'id' => 7, 'label' => 'صفحه دوم', 'description' => 'کنترل بعد از AJAX rerender', 'type' => 'text', 'isRequired' => true ),
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
			array( 'id' => 1, 'label' => 'Legacy text', 'description' => 'Legacy description', 'type' => 'text' ),
			array(
				'id'      => 2,
				'label'   => 'Legacy select',
				'type'    => 'select',
				'choices' => array( array( 'text' => 'Legacy option', 'value' => 'legacy' ) ),
			),
		),
		'button' => array( 'type' => 'text', 'text' => 'Legacy submit' ),
	)
);

$legacy_steps_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Legacy Steps',
		'description'    => 'Real Legacy Markup multipage step typography fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 1,
		'pagination'     => array(
			'type'  => 'steps',
			'pages' => array( 'مرحله اول', 'مرحله دوم' ),
			'style' => 'blue',
		),
		'fields' => array(
			array( 'id' => 1, 'label' => 'Legacy steps field', 'description' => 'Legacy steps ordinary field', 'type' => 'text' ),
			array(
				'id'             => 2,
				'label'          => 'Legacy steps page break',
				'type'           => 'page',
				'nextButton'     => array( 'type' => 'text', 'text' => 'Legacy next' ),
				'previousButton' => array( 'type' => 'text', 'text' => 'Legacy previous' ),
			),
			array( 'id' => 3, 'label' => 'Legacy steps page two', 'type' => 'text' ),
		),
		'button' => array( 'type' => 'text', 'text' => 'Legacy steps submit' ),
	)
);

$legacy_percentage_form_id = vazir_gf_lab_add_form(
	array(
		'title'          => 'Vazir GF Evidence — Legacy Percentage',
		'description'    => 'Real Legacy Markup multipage percentage typography fixture.',
		'labelPlacement' => 'top_label',
		'markupVersion'  => 1,
		'pagination'     => array(
			'type'  => 'percentage',
			'pages' => array( 'بخش اول', 'بخش دوم' ),
			'style' => 'blue',
		),
		'fields' => array(
			array( 'id' => 1, 'label' => 'Legacy percentage field', 'description' => 'Legacy percentage ordinary field', 'type' => 'text' ),
			array(
				'id'             => 2,
				'label'          => 'Legacy percentage page break',
				'type'           => 'page',
				'nextButton'     => array( 'type' => 'text', 'text' => 'Percentage next' ),
				'previousButton' => array( 'type' => 'text', 'text' => 'Percentage previous' ),
			),
			array( 'id' => 3, 'label' => 'Legacy percentage page two', 'type' => 'text' ),
		),
		'button' => array( 'type' => 'text', 'text' => 'Percentage submit' ),
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
$legacy_steps_post_id = vazir_gf_lab_add_page(
	'Vazir GF Legacy Steps Evidence',
	'vazir-gf-legacy-steps-evidence',
	sprintf( '[gravityform id="%d" title="true" description="true" ajax="false" theme="legacy"]', $legacy_steps_form_id )
);
$legacy_percentage_post_id = vazir_gf_lab_add_page(
	'Vazir GF Legacy Percentage Evidence',
	'vazir-gf-legacy-percentage-evidence',
	sprintf( '[gravityform id="%d" title="true" description="true" ajax="false" theme="legacy"]', $legacy_percentage_form_id )
);

for ( $index = 1; $index <= 35; $index++ ) {
	vazir_gf_lab_add_entry(
		array(
			'form_id' => $dynamic_form_id,
			'1'       => sprintf( 'Admin evidence entry %02d', $index ),
			'7'       => sprintf( 'Second page %02d', $index ),
		)
	);
}

$manifest = array(
	'gravity_forms_version'       => (string) GFForms::$version,
	'orbital_form_id'             => $orbital_form_id,
	'dynamic_form_id'             => $dynamic_form_id,
	'legacy_form_id'              => $legacy_form_id,
	'legacy_steps_form_id'        => $legacy_steps_form_id,
	'legacy_percentage_form_id'   => $legacy_percentage_form_id,
	'orbital_post_id'             => $orbital_post_id,
	'dynamic_post_id'             => $dynamic_post_id,
	'legacy_post_id'              => $legacy_post_id,
	'legacy_steps_post_id'        => $legacy_steps_post_id,
	'legacy_percentage_post_id'   => $legacy_percentage_post_id,
	'orbital_url'                 => get_permalink( $orbital_post_id ),
	'dynamic_url'                 => get_permalink( $dynamic_post_id ),
	'legacy_url'                  => get_permalink( $legacy_post_id ),
	'legacy_steps_url'            => get_permalink( $legacy_steps_post_id ),
	'legacy_percentage_url'       => get_permalink( $legacy_percentage_post_id ),
	'preview_url'                 => add_query_arg( array( 'gf_page' => 'preview', 'id' => $dynamic_form_id ), home_url( '/' ) ),
	'form_editor_url'             => admin_url( 'admin.php?page=gf_edit_forms&id=' . $dynamic_form_id ),
	'forms_admin_url'             => admin_url( 'admin.php?page=gf_edit_forms' ),
	'entries_admin_url'           => admin_url( 'admin.php?page=gf_entries&id=' . $dynamic_form_id ),
	'no_conflict_mode'            => (bool) get_option( 'gform_enable_noconflict' ),
	'orbital_markup_version'      => 2,
	'dynamic_markup_version'      => 2,
	'legacy_markup_version'       => 1,
	'legacy_steps_markup_version' => 1,
	'legacy_percentage_version'   => 1,
	'admin_entry_count'           => 35,
	'exclude_selector'            => '.vf-gf-excluded',
);

update_option( 'vazir_gf_evidence_fixture_manifest', $manifest, false );
file_put_contents(
	$artifact_dir . '/fixture-manifest.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
