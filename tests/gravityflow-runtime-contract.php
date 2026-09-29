<?php
declare(strict_types=1);

$GLOBALS['vf_flow_actions'] = array();
$GLOBALS['vf_flow_filters'] = array();
$GLOBALS['vf_flow_styles'] = array();
$GLOBALS['vf_flow_inline'] = array();
$GLOBALS['vf_flow_options'] = array();

const ABSPATH = '/tmp/wp/';
const GRAVITY_FLOW_VERSION = '3.1.0';

function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/vazir/'; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['vf_flow_actions'][$hook][] = array( $callback, $priority, $accepted_args ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['vf_flow_filters'][$hook][] = array( $callback, $priority, $accepted_args ); }
function apply_filters( $hook, $value ) { return $value; }
function load_plugin_textdomain( ...$args ) { return true; }
function plugin_basename( $file ) { return basename( $file ); }
function get_option( $name, $default = false ) { return $GLOBALS['vf_flow_options'][$name] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['vf_flow_options'][$name] = $value; return true; }
function is_admin() { return false; }
function wp_clear_scheduled_hook( $hook ) { return 1; }
function esc_url( $url ) { return $url; }
function wp_register_style( $handle, $src = false, $deps = array(), $ver = false ) { $GLOBALS['vf_flow_styles'][$handle] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => false ); return true; }
function wp_enqueue_style( $handle ) { $GLOBALS['vf_flow_styles'][$handle]['enqueued'] = true; }
function wp_add_inline_style( $handle, $css ) { $GLOBALS['vf_flow_inline'][$handle][] = $css; return true; }
function is_rtl() { return true; }

class Gravity_Flow {}

function vf_flow_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

function vf_flow_reset_adapter( VazirFont_GravityFlow_Integration $integration ): void {
	$reflection = new ReflectionClass( $integration );
	foreach ( array( 'cached_css' => null, 'inline_handles' => array() ) as $name => $value ) {
		$property = $reflection->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $integration, $value );
	}
	$GLOBALS['vf_flow_styles'] = array();
	$GLOBALS['vf_flow_inline'] = array();
}

require dirname( __DIR__ ) . '/vazir-font-wp.php';
VazirFontPlugin::get_instance()->init();

vf_flow_assert( isset( $GLOBALS['vf_flow_actions']['gravityflow_enqueue_admin_scripts'] ), 'Gravity Flow admin enqueue seam is registered.' );
vf_flow_assert( isset( $GLOBALS['vf_flow_actions']['gravityflow_enqueue_frontend_scripts'] ), 'Gravity Flow frontend enqueue seam is registered.' );

$integration = VazirFont_GravityFlow_Integration::get_instance();
$reflection = new ReflectionClass( $integration );
$available = $reflection->getProperty( 'flow_available' );
$available->setAccessible( true );
vf_flow_assert( true === $available->getValue( $integration ), 'Exact Gravity Flow 3.1.0 runtime is admitted.' );

$integration->enqueue_admin_assets();
$admin_handle = 'vazir-font-gravity-flow-admin';
vf_flow_assert( isset( $GLOBALS['vf_flow_styles'][ $admin_handle ] ), 'Gravity Flow admin style handle is registered.' );
vf_flow_assert( array( 'gravityflow_admin_css' ) === $GLOBALS['vf_flow_styles'][ $admin_handle ]['deps'], 'Admin style depends on Gravity Flow admin CSS.' );
vf_flow_assert( true === $GLOBALS['vf_flow_styles'][ $admin_handle ]['enqueued'], 'Gravity Flow admin style handle is enqueued.' );
$css = implode( "\n", $GLOBALS['vf_flow_inline'][ $admin_handle ] ?? array() );
vf_flow_assert( false !== strpos( $css, '.gflow-grid .ag-theme-alpine' ), 'AG Grid theme root correction is present.' );
vf_flow_assert( false !== strpos( $css, '.ag-input-wrapper.custom-date-filter input' ), 'AG Grid date-filter input correction is present.' );
vf_flow_assert( false !== strpos( $css, 'input[class^="ag-"]' ), 'AG Grid text-input correction is present.' );
vf_flow_assert( false !== strpos( $css, '.flatpickr-calendar.ag-custom-component-popup' ), 'Flow-bound Flatpickr correction is present.' );
vf_flow_assert( false === strpos( $css, '@font-face' ), 'Gravity Flow adapter does not duplicate font-face delivery.' );
vf_flow_assert( false === strpos( $css, "* {\n\tfont-family:" ), 'No blanket descendant font override is emitted.' );
vf_flow_assert( false === strpos( $css, 'font-family: "agGridAlpine"' ), 'Adapter does not replace AG Grid icon-family ownership.' );
vf_flow_assert( false === strpos( $css, 'font-family: "gflow-icons-common"' ), 'Adapter does not replace Gravity Flow icon-family ownership.' );

VazirFontPlugin::update_options( array( 'exclude_selectors' => array( '.ag-paging-panel', '[data-icon]:before' ) ) );
vf_flow_reset_adapter( $integration );
$integration->enqueue_frontend_assets();
$frontend_handle = 'vazir-font-gravity-flow-frontend';
vf_flow_assert( array( 'gravityflow_theme_css' ) === $GLOBALS['vf_flow_styles'][ $frontend_handle ]['deps'], 'Frontend style depends on Gravity Flow theme CSS.' );
$excluded_css = implode( "\n", $GLOBALS['vf_flow_inline'][ $frontend_handle ] ?? array() );
$guard = ':not(:where(.ag-paging-panel, .ag-paging-panel *)):not(:has(:where(.ag-paging-panel)))';
vf_flow_assert( false !== strpos( $excluded_css, '.gflow-grid .ag-theme-alpine' . $guard ), 'Inheritable AG Grid rule fails closed across an excluded descendant subtree.' );
vf_flow_assert( false === strpos( $excluded_css, '[data-icon]:before' . $guard ), 'Pseudo-element exclusions are not forced into relational element guards.' );

VazirFontPlugin::update_options( array( 'enable_admin' => false, 'enable_gravity_forms' => true ) );
vf_flow_reset_adapter( $integration );
$integration->enqueue_admin_assets();
vf_flow_assert( array() === $GLOBALS['vf_flow_styles'], 'Existing admin typography toggle disables admin Gravity Flow repair.' );

VazirFontPlugin::update_options( array( 'enable_admin' => true, 'enable_frontend' => true, 'enable_gravity_forms' => false ) );
vf_flow_reset_adapter( $integration );
$integration->enqueue_frontend_assets();
vf_flow_assert( array() === $GLOBALS['vf_flow_styles'], 'Existing Gravity compatibility toggle disables the Gravity Flow adapter.' );

fwrite( STDOUT, "ALL GRAVITY FLOW CONTRACT CHECKS PASSED\n" );
