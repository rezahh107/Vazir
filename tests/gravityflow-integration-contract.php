<?php
declare(strict_types=1);

$GLOBALS['vf_flow_actions'] = array();
$GLOBALS['vf_flow_styles']  = array();
$GLOBALS['vf_flow_inline']  = array();
$GLOBALS['vf_flow_options'] = array(
	'enable_frontend'      => true,
	'enable_admin'         => true,
	'enable_gravity_forms' => true,
	'exclude_selectors'    => array( '.vf-flow-excluded', '[data-icon]:before' ),
);

const ABSPATH = '/tmp/wp/';
const VAZIR_FONT_VERSION = '1.3.0';
const GRAVITY_FLOW_VERSION = '3.1.0';

class Gravity_Flow {}

final class VazirFontPlugin {
	public static function get_options(): array {
		return $GLOBALS['vf_flow_options'];
	}
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['vf_flow_actions'][ $hook ][] = array( $callback, $priority, $accepted_args );
}
function apply_filters( $hook, $value ) { return $value; }
function wp_register_style( $handle, $src = false, $deps = array(), $ver = false ) {
	$GLOBALS['vf_flow_styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => false );
	return true;
}
function wp_enqueue_style( $handle ) {
	if ( isset( $GLOBALS['vf_flow_styles'][ $handle ] ) ) {
		$GLOBALS['vf_flow_styles'][ $handle ]['enqueued'] = true;
	}
}
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['vf_flow_inline'][ $handle ][] = $css;
	return true;
}

function vf_flow_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

require dirname( __DIR__ ) . '/includes/class-vazirfont-gravityflow-integration.php';

$integration = VazirFont_GravityFlow_Integration::get_instance();
$reflection  = new ReflectionClass( $integration );
$available   = $reflection->getProperty( 'flow_available' );
$available->setAccessible( true );

vf_flow_assert( true === $available->getValue( $integration ), 'exact Gravity Flow 3.1.0 runtime is admitted' );
vf_flow_assert( isset( $GLOBALS['vf_flow_actions']['gravityflow_enqueue_admin_scripts'] ), 'supported admin enqueue seam is registered' );
vf_flow_assert( isset( $GLOBALS['vf_flow_actions']['gravityflow_enqueue_frontend_scripts'] ), 'supported frontend enqueue seam is registered' );

$integration->enqueue_admin_assets();
$admin_handle = 'vazir-font-gravity-flow-admin';
vf_flow_assert( ! empty( $GLOBALS['vf_flow_styles'][ $admin_handle ]['enqueued'] ), 'admin compatibility handle is enqueued' );
vf_flow_assert( array( 'gravityflow_admin_css' ) === $GLOBALS['vf_flow_styles'][ $admin_handle ]['deps'], 'admin handle depends on Gravity Flow admin CSS' );
$admin_css = implode( "\n", $GLOBALS['vf_flow_inline'][ $admin_handle ] ?? array() );
vf_flow_assert( false !== strpos( $admin_css, '.gflow-grid .ag-theme-alpine' ), 'AG Grid theme root receives bounded text-family repair' );
vf_flow_assert( false !== strpos( $admin_css, 'input[class^="ag-"]' ), 'AG Grid text inputs receive direct repair' );
vf_flow_assert( false !== strpos( $admin_css, '.flatpickr-calendar.ag-custom-component-popup' ), 'Gravity Flow Flatpickr popup receives direct repair' );
vf_flow_assert( false !== strpos( $admin_css, ':not(:where(.vf-flow-excluded, .vf-flow-excluded *))' ), 'configured element exclusion protects roots and descendants' );
vf_flow_assert( false !== strpos( $admin_css, ':not(:has(:where(.vf-flow-excluded)))' ), 'inheritable rules fail closed around excluded subtrees' );
vf_flow_assert( false === strpos( $admin_css, '[data-icon]:before' ), 'pseudo-element exclusions are not forced into relational guards' );
vf_flow_assert( false === strpos( $admin_css, '@font-face' ), 'adapter does not duplicate bundled font delivery' );
vf_flow_assert( false === strpos( $admin_css, '.ag-icon {' ), 'adapter does not overwrite AG Grid icon-family nodes' );
vf_flow_assert( false === strpos( $admin_css, '.gflow-icon' ), 'adapter does not overwrite Gravity Flow icon-family nodes' );

$integration->enqueue_frontend_assets();
$frontend_handle = 'vazir-font-gravity-flow-frontend';
vf_flow_assert( ! empty( $GLOBALS['vf_flow_styles'][ $frontend_handle ]['enqueued'] ), 'frontend compatibility handle is enqueued' );
vf_flow_assert( array( 'gravityflow_theme_css' ) === $GLOBALS['vf_flow_styles'][ $frontend_handle ]['deps'], 'frontend handle depends on Gravity Flow theme CSS' );

$GLOBALS['vf_flow_options']['enable_admin'] = false;
unset( $GLOBALS['vf_flow_styles'][ $admin_handle ] );
$integration->enqueue_admin_assets();
vf_flow_assert( ! isset( $GLOBALS['vf_flow_styles'][ $admin_handle ] ), 'admin Flow repair respects the existing admin typography toggle' );

$GLOBALS['vf_flow_options']['enable_admin']         = true;
$GLOBALS['vf_flow_options']['enable_gravity_forms'] = false;
unset( $GLOBALS['vf_flow_styles'][ $frontend_handle ] );
$integration->enqueue_frontend_assets();
vf_flow_assert( ! isset( $GLOBALS['vf_flow_styles'][ $frontend_handle ] ), 'Flow repair respects the existing Gravity compatibility toggle' );

fwrite( STDOUT, "ALL GRAVITY FLOW CONTRACT CHECKS PASSED\n" );
