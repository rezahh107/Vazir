<?php
/** Build a real test-only Perk plugin and exact Gravity Perks route fixtures. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
wp_mkdir_p( $artifact_dir );
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) || ! is_plugin_active( 'gravityperks/gravityperks.php' ) ) { throw new RuntimeException( 'Gravity Forms and Gravity Perks must be active before fixture creation.' ); }
if ( ! class_exists( 'GWPerk' ) || ! class_exists( 'GP_Perk' ) ) { throw new RuntimeException( 'Exact Gravity Perks Perk API is unavailable.' ); }

$fixture_dir = WP_PLUGIN_DIR . '/gp-vazir-evidence';
$fixture_file = $fixture_dir . '/gp-vazir-evidence.php';
$fixture_basename = 'gp-vazir-evidence/gp-vazir-evidence.php';
wp_mkdir_p( $fixture_dir );
$fixture_source = <<<'PHP'
<?php
/**
 * Plugin Name: GP Vazir Evidence
 * Plugin URI: https://example.invalid/vazir-gp-evidence
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

<div class="vazir-gp-evidence-excluded" id="vazir-gp-doc-excluded" style="font-family: monospace;">Excluded standalone document evidence</div>
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
			'options' => array( 'one' => 'Evidence One', 'two' => 'Evidence Two' ),
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

// Evidence-only sentinels. They do not change typography. The first asks whether
// inline CSS attached to Gravity Perks' own handle survives the standalone
// wp_print_styles() boundary. The second proves which links traverse the normal
// WordPress style_loader_tag pipeline; the literal Google Fonts link should not.
add_action( 'admin_enqueue_scripts', function() {
	if ( ! isset( $_GET['page'] ) || 'gwp_perks' !== $_GET['page'] ) { return; }
	if ( wp_style_is( 'gwp-admin', 'registered' ) ) {
		$options = class_exists( 'VazirFontPlugin' ) ? VazirFontPlugin::get_options() : array();
		$exclusions = isset( $options['exclude_selectors'] ) && is_array( $options['exclude_selectors'] ) ? count( $options['exclude_selectors'] ) : -1;
		wp_add_inline_style( 'gwp-admin', ':root{--vazir-gravityperks-gwp-admin-seam-probe:1;--vazir-gravityperks-exclusion-count:' . (int) $exclusions . ';}' );
	}
}, 999 );

add_filter( 'style_loader_tag', function( $html, $handle ) {
	if ( 'gwp-admin' === $handle ) {
		return str_replace( '<link ', '<link data-vazir-gp-style-loader-probe="gwp-admin" ', $html );
	}
	if ( 'google-fonts' === $handle ) {
		return str_replace( '<link ', '<link data-vazir-gp-style-loader-probe="google-fonts" ', $html );
	}
	return $html;
}, 999, 2 );
PHP;
if ( false === file_put_contents( $fixture_file, $fixture_source ) ) { throw new RuntimeException( 'Could not create the test-only Perk plugin fixture.' ); }

$perk_data = GP_Perk::get_perk_data( $fixture_basename, true );
if ( ! is_array( $perk_data ) || 'True' !== (string) ( $perk_data['Perk'] ?? '' ) ) { throw new RuntimeException( 'Gravity Perks did not recognize the Perk: True fixture header.' ); }
$result = activate_plugin( $fixture_basename );
if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
if ( ! is_plugin_active( $fixture_basename ) ) { throw new RuntimeException( 'Test-only Perk fixture did not activate.' ); }

$perk = GP_Perk::get_perk( $fixture_basename );
if ( is_wp_error( $perk ) || ! $perk instanceof GP_Perk || ! $perk instanceof GP_Vazir_Evidence ) { throw new RuntimeException( 'Gravity Perks did not instantiate the real test Perk through GP_Perk::get_perk().' ); }
$documentation_url = html_entity_decode( (string) $perk->get_link_for( 'documentation', $fixture_basename ), ENT_QUOTES, 'UTF-8' );
$settings_url = html_entity_decode( (string) $perk->get_link_for( 'settings', $fixture_basename ), ENT_QUOTES, 'UTF-8' );
if ( '' === $documentation_url || '' === $settings_url ) { throw new RuntimeException( 'Gravity Perks did not generate Documentation/Settings URLs for the real test Perk.' ); }

$options = get_option( 'vazir_font_options', array() );
if ( ! is_array( $options ) ) { $options = array(); }
$options['enable_admin'] = true;
$options['enable_gravity_forms'] = true;
$options['exclude_selectors'] = array( '.vazir-gp-evidence-excluded' );
update_option( 'vazir_font_options', $options, false );

$manifest = array(
	'gravity_perks_version' => (string) ( get_plugin_data( WP_PLUGIN_DIR . '/gravityperks/gravityperks.php', false, false )['Version'] ?? '' ),
	'gravity_forms_version' => class_exists( 'GFForms' ) && isset( GFForms::$version ) ? (string) GFForms::$version : null,
	'fixture_plugin' => $fixture_basename,
	'fixture_class' => get_class( $perk ),
	'fixture_perk_header' => (string) ( $perk_data['Perk'] ?? '' ),
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
