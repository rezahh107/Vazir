<?php
declare(strict_types=1);

$GLOBALS['vf_actions']  = array();
$GLOBALS['vf_filters']  = array();
$GLOBALS['vf_styles']   = array();
$GLOBALS['vf_inline']   = array();
$GLOBALS['vf_options']  = array();
$GLOBALS['vf_is_admin'] = true;

const ABSPATH = '/tmp/wp/';

function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/vazir-font-wp/'; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['vf_actions'][ $hook ][] = array( $callback, $priority, $accepted_args ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['vf_filters'][ $hook ][] = array( $callback, $priority, $accepted_args ); }
function load_plugin_textdomain( ...$args ) { return true; }
function plugin_basename( $file ) { return basename( $file ); }
function get_option( $name, $default = false ) { return $GLOBALS['vf_options'][ $name ] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['vf_options'][ $name ] = $value; return true; }
function is_admin() { return (bool) $GLOBALS['vf_is_admin']; }
function wp_clear_scheduled_hook( $hook ) { return 1; }
function apply_filters( $hook, $value ) { return $value; }
function esc_url( $url ) { return $url; }
function wp_register_style( $handle, $src = false, $deps = array(), $ver = false ) { $GLOBALS['vf_styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'registered' => true, 'enqueued' => false ); return true; }
function wp_enqueue_style( $handle ) { if ( isset( $GLOBALS['vf_styles'][ $handle ] ) ) { $GLOBALS['vf_styles'][ $handle ]['enqueued'] = true; } }
function wp_add_inline_style( $handle, $css ) { if ( ! wp_style_is( $handle, 'registered' ) ) { return false; } $GLOBALS['vf_inline'][ $handle ][] = $css; return true; }
function wp_style_is( $handle, $status = 'enqueued' ) { if ( ! isset( $GLOBALS['vf_styles'][ $handle ] ) ) { return false; } return 'registered' === $status ? ! empty( $GLOBALS['vf_styles'][ $handle ]['registered'] ) : ! empty( $GLOBALS['vf_styles'][ $handle ]['enqueued'] ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function is_rtl() { return true; }

class GravityPerks {}
class GWPerksPage {
	public static function load_perk_settings(): void {}
}

function vf_gp_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

function vf_gp_reset( VazirFont_GravityPerks_Integration $integration ): void {
	$reflection = new ReflectionClass( $integration );
	foreach ( array( 'inline_attached' => false, 'cached_css' => null ) as $name => $value ) {
		$property = $reflection->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $integration, $value );
	}
	$GLOBALS['vf_inline'][ 'gwp-admin' ] = array();
}

require dirname( __DIR__ ) . '/vazir-font-wp.php';
VazirFontPlugin::get_instance()->init();

vf_gp_assert( class_exists( 'VazirFont_GravityPerks_Integration' ), 'Gravity Perks adapter autoloads from the repository-native class mapping' );
$integration = VazirFont_GravityPerks_Integration::get_instance();
vf_gp_assert( isset( $GLOBALS['vf_filters']['print_styles_array'] ), 'adapter registers the supported print_styles_array seam before standalone output' );

wp_register_style( 'gwp-admin', 'https://example.test/gravityperks/admin.css', array(), '2.3.16' );
VazirFontPlugin::update_options(
	array(
		'enable_admin'         => true,
		'enable_gravity_forms' => true,
		'exclude_selectors'    => array( '.vazir-gp-evidence-excluded', '[data-icon]:before' ),
	)
);
$_GET = array( 'page' => 'gwp_perks', 'view' => 'perk_settings', 'slug' => 'gp-vazir-evidence/gp-vazir-evidence.php' );
$handles = array( 'gwp-admin', 'wp-admin', 'buttons', 'colors-fresh' );
$returned = $integration->filter_print_styles_array( $handles );
vf_gp_assert( $handles === $returned, 'print_styles_array returns the authentic Gravity Perks style handle list unchanged' );
$css = implode( "\n", $GLOBALS['vf_inline']['gwp-admin'] ?? array() );
vf_gp_assert( '' !== $css, 'repair attaches inline CSS to the already-registered gwp-admin host handle' );
vf_gp_assert( 5 === substr_count( $css, '@font-face' ), 'repair reuses Loader font-face delivery for the configured default weights' );
vf_gp_assert( false !== strpos( $css, 'vazirmatn-400.woff2' ), 'repair font faces point at the bundled Vazirmatn assets' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings .page-title' ), 'page title is inside the bounded standalone Settings selector set' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings label' ), 'setting labels are inside the bounded standalone Settings selector set' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings .description' ), 'setting descriptions are inside the bounded standalone Settings selector set' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings input[type="text"]' ), 'text controls are inside the bounded standalone Settings selector set' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings select' ), 'select controls are inside the bounded standalone Settings selector set' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings textarea' ), 'textarea controls are supported without requiring the fixture to render one' );
vf_gp_assert( false !== strpos( $css, 'body.perk-iframe .perk-settings #gwp_save_settings' ), 'save button is inside the bounded standalone Settings selector set' );
$guard = ':not(:where(.vazir-gp-evidence-excluded, .vazir-gp-evidence-excluded *))';
vf_gp_assert( false !== strpos( $css, $guard ), 'existing exclusion authority blocks the excluded root and descendants' );
vf_gp_assert( false === strpos( $css, '.perk-iframe *' ), 'repair does not introduce a blanket perk-iframe descendant override' );
vf_gp_assert( false === strpos( $css, 'input[type="checkbox"]' ) && false === strpos( $css, 'input[type="radio"]' ), 'checkbox/radio glyphs are not treated as text typography' );
vf_gp_assert( false === strpos( $css, '[data-icon]:before' . $guard ), 'pseudo-element exclusions are not converted into text enforcement targets' );
vf_gp_assert( false === strpos( $css, 'font-family: inherit' ), 'exclusions remain negative applicability boundaries rather than competing reset rules' );
vf_gp_assert( array( 'gwp-admin' ) === array_keys( $GLOBALS['vf_styles'] ), 'repair creates no replacement or standalone Vazir stylesheet handle' );

vf_gp_reset( $integration );
$_GET = array( 'page' => 'gwp_perks' );
$integration->filter_print_styles_array( $handles );
vf_gp_assert( array() === $GLOBALS['vf_inline']['gwp-admin'], 'ordinary Gravity Perks admin without view receives no standalone repair CSS' );

vf_gp_reset( $integration );
$_GET = array( 'page' => 'gwp_perks', 'view' => 'perk_settings', 'slug' => 'fixture' );
$integration->filter_print_styles_array( array( 'wp-admin', 'buttons' ) );
vf_gp_assert( array() === $GLOBALS['vf_inline']['gwp-admin'], 'repair fails closed when gwp-admin is absent from the styles being processed' );

vf_gp_reset( $integration );
VazirFontPlugin::update_options( array( 'enable_admin' => false, 'enable_gravity_forms' => true ) );
$integration->filter_print_styles_array( $handles );
vf_gp_assert( array() === $GLOBALS['vf_inline']['gwp-admin'], 'existing admin toggle disables standalone Perks repair' );

vf_gp_reset( $integration );
VazirFontPlugin::update_options( array( 'enable_admin' => true, 'enable_gravity_forms' => false ) );
$integration->filter_print_styles_array( $handles );
vf_gp_assert( array() === $GLOBALS['vf_inline']['gwp-admin'], 'existing Gravity compatibility toggle disables standalone Perks repair' );

vf_gp_reset( $integration );
VazirFontPlugin::update_options( array( 'enable_admin' => true, 'enable_gravity_forms' => true, 'exclude_selectors' => array( '.safe', 'broken{selector' ) ) );
$integration->filter_print_styles_array( $handles );
vf_gp_assert( array() === $GLOBALS['vf_inline']['gwp-admin'], 'unrepresentable exclusion causes the bounded Perks repair to fail closed' );

fwrite( STDOUT, "ALL GRAVITY PERKS CONTRACT CHECKS PASSED\n" );
