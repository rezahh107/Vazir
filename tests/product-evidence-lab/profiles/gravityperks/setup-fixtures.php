<?php
/** Build a real test-only Perk plugin and exact Gravity Perks route fixtures. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
wp_mkdir_p( $artifact_dir );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) || ! is_plugin_active( 'gravityperks/gravityperks.php' ) ) {
	throw new RuntimeException( 'Gravity Forms and Gravity Perks must be active before fixture creation.' );
}
if ( ! class_exists( 'GWPerk' ) || ! class_exists( 'GP_Perk' ) ) {
	throw new RuntimeException( 'Exact Gravity Perks Perk API is unavailable.' );
}

$fixture_dir = WP_PLUGIN_DIR . '/gp-vazir-evidence';
$fixture_file = $fixture_dir . '/gp-vazir-evidence.php';
$fixture_basename = 'gp-vazir-evidence/gp-vazir-evidence.php';
wp_mkdir_p( $fixture_dir );
$fixture_source = <<<'PHP'
<?php
/**
 * Plugin Name: GP Vazir Evidence
 * Description: Ephemeral real-Perk fixture for Vazir qualification only.
 * Version: 1.0.0
 * Author: Vazir Evidence Lab
 * Perk: True
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GP_Vazir_Evidence extends GWPerk {
	public $version = '1.0.0';
	protected $min_gravity_perks_version = '2.3.16';
	protected $min_gravity_forms_version = '3.1.1.1';

	public function documentation() {
		return <<<'DOC'
## Vazir Perk Documentation Heading

<p id="vazir-gp-doc-paragraph">Vazir Perk documentation paragraph متن آزمایشی.</p>

<ul id="vazir-gp-doc-list"><li>Evidence item <span class="description" id="vazir-gp-doc-description">Vazir Perk description متن توضیح</span></li></ul>

<div class="footer" id="vazir-gp-doc-footer"><a id="vazir-gp-doc-link" href="#evidence">Vazir Perk footer link</a></div>
DOC;
	}

	public function settings() {
		echo self::generate_input( $this, array(
			'id' => 'evidence_text',
			'label' => 'Vazir Evidence Text',
			'description' => 'Vazir evidence text description',
		) );
		echo self::generate_select( $this, array(
			'id' => 'evidence_select',
			'label' => 'Vazir Evidence Select',
			'description' => 'Vazir evidence select description',
			'options' => array(
				'one' => 'Evidence One',
				'two' => 'Evidence Two',
			),
		) );
		echo self::generate_checkbox( $this, array(
			'id' => 'evidence_checkbox',
			'label' => 'Vazir Evidence Checkbox',
			'description' => 'Vazir evidence checkbox description',
		) );
	}

	public function register_settings( $perk ) {
		return array( 'evidence_text', 'evidence_select', 'evidence_checkbox' );
	}
}

// Evidence-only sentinel: test whether WordPress inline CSS attached to Gravity
// Perks' own gwp-admin handle survives its standalone-document print boundary.
// It deliberately changes no typography.
add_action( 'admin_enqueue_scripts', function() {
	if ( isset( $_GET['page'] ) && 'gwp_perks' === $_GET['page'] ) {
		wp_add_inline_style( 'gwp-admin', ':root{--vazir-gravityperks-gwp-admin-seam-probe:1;}' );
	}
}, 999 );
PHP;
if ( false === file_put_contents( $fixture_file, $fixture_source ) ) {
	throw new RuntimeException( 'Could not create the test-only Perk plugin fixture.' );
}

$plugin_data = get_plugin_data( $fixture_file, false, false );
if ( 'True' !== (string) ( $plugin_data['Perk'] ?? '' ) ) {
	// WordPress core does not know the custom Perk header; Gravity Perks does.
	$perk_data = GP_Perk::get_perk_data( $fixture_basename, true );
	if ( ! is_array( $perk_data ) || 'True' !== (string) ( $perk_data['Perk'] ?? '' ) ) {
		throw new RuntimeException( 'Gravity Perks did not recognize the Perk: True fixture header.' );
	}
}

$result = activate_plugin( $fixture_basename );
if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
if ( ! is_plugin_active( $fixture_basename ) ) { throw new RuntimeException( 'Test-only Perk fixture did not activate.' ); }

$perk = GP_Perk::get_perk( $fixture_basename );
if ( is_wp_error( $perk ) || ! $perk instanceof GP_Perk ) {
	throw new RuntimeException( 'Gravity Perks did not instantiate the real test Perk through GP_Perk::get_perk().' );
}
if ( ! $perk instanceof GP_Vazir_Evidence ) { throw new RuntimeException( 'Unexpected test Perk class.' ); }

$documentation_url = $perk->get_link_for( 'documentation', $fixture_basename );
$settings_url = $perk->get_link_for( 'settings', $fixture_basename );
if ( ! is_string( $documentation_url ) || '' === $documentation_url || ! is_string( $settings_url ) || '' === $settings_url ) {
	throw new RuntimeException( 'Gravity Perks did not generate Documentation/Settings URLs for the real test Perk.' );
}

$options = get_option( 'vazir_font_options', array() );
if ( ! is_array( $options ) ) { $options = array(); }
$options['enable_admin'] = true;
$options['enable_gravity_forms'] = true;
$options['exclude_selectors'] = array( '.vazir-gp-evidence-excluded' );
update_option( 'vazir_font_options', $options, false );

$manifest = array(
	'gravity_perks_version' => defined( 'GRAVITY_PERKS_VERSION' ) ? (string) GRAVITY_PERKS_VERSION : (string) ( get_plugin_data( WP_PLUGIN_DIR . '/gravityperks/gravityperks.php', false, false )['Version'] ?? '' ),
	'gravity_forms_version' => class_exists( 'GFForms' ) && isset( GFForms::$version ) ? (string) GFForms::$version : null,
	'fixture_plugin' => $fixture_basename,
	'fixture_class' => get_class( $perk ),
	'fixture_perk_header' => 'True',
	'fixture_is_perk' => GP_Perk::is_perk( $fixture_basename, true ),
	'normal_admin_url' => admin_url( 'admin.php?page=gwp_perks' ),
	'documentation_url' => $documentation_url,
	'settings_url' => $settings_url,
	'perk_slug' => $perk->get_property( 'slug' ),
	'perk_basename' => $perk->get_property( 'basename' ),
	'exclude_selectors' => $options['exclude_selectors'],
	'fixture_shipped_in_production' => false,
);
file_put_contents( $artifact_dir . '/fixture-manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES ) . "\n";
