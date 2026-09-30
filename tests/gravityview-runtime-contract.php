<?php
declare(strict_types=1);

const ABSPATH = '/tmp/wp/';
define( 'GRAVITYVIEW_FILE', '/tmp/wp-content/plugins/gravityview/gravityview.php' );

$GLOBALS['vf_gv_actions'] = array();
$GLOBALS['vf_gv_styles']  = array();
$GLOBALS['vf_gv_inline']  = array();
$GLOBALS['vf_gv_admin']   = true;

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['vf_gv_actions'][ $hook ][] = array( $callback, $priority, $accepted_args );
}
function is_admin() { return (bool) $GLOBALS['vf_gv_admin']; }
function apply_filters( $hook, $value ) { return $value; }
function wp_style_is( $handle, $status = 'enqueued' ) {
	return isset( $GLOBALS['vf_gv_styles'][ $handle ] ) && 'registered' === $status && ! empty( $GLOBALS['vf_gv_styles'][ $handle ]['registered'] );
}
function wp_add_inline_style( $handle, $css ) {
	if ( ! wp_style_is( $handle, 'registered' ) ) {
		return false;
	}
	$GLOBALS['vf_gv_inline'][ $handle ][] = $css;
	return true;
}

final class VazirFontPlugin {
	private static array $options = array(
		'enable_admin'         => true,
		'enable_gravity_forms' => true,
		'exclude_selectors'    => array(),
	);

	public static function get_options(): array { return self::$options; }
	public static function update_options( array $options ): void { self::$options = array_merge( self::$options, $options ); }
}

class WP_Block_Type {
	public array $editor_style_handles = array();
}

final class WP_Block_Type_Registry {
	private static ?self $instance = null;
	private array $blocks = array();

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register( string $name, WP_Block_Type $block ): void { $this->blocks[ $name ] = $block; }
	public function unregister( string $name ): void { unset( $this->blocks[ $name ] ); }
	public function get_registered( string $name ) { return $this->blocks[ $name ] ?? null; }
}

function vf_gv_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

function vf_gv_private_invoke( object $object, string $method_name, array $arguments = array() ) {
	$method = ( new ReflectionClass( $object ) )->getMethod( $method_name );
	$method->setAccessible( true );
	return $method->invokeArgs( $object, $arguments );
}

function vf_gv_reset( VazirFont_GravityView_Integration $integration ): void {
	$reflection = new ReflectionClass( $integration );
	foreach ( array( 'inline_attached' => false, 'cached_css' => null ) as $name => $value ) {
		$property = $reflection->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $integration, $value );
	}
	$GLOBALS['vf_gv_inline'] = array();
}

$bootstrap_source = file_get_contents( dirname( __DIR__ ) . '/vazir-font-wp.php' );
vf_gv_assert( is_string( $bootstrap_source ), 'plugin bootstrap source is readable' );
vf_gv_assert( false !== strpos( $bootstrap_source, 'VazirFont_GravityView_Integration::get_instance()' ), 'plugin bootstrap initializes the GravityView adapter when the runtime is present' );

require dirname( __DIR__ ) . '/includes/class-vazirfont-selector-boundary.php';
require dirname( __DIR__ ) . '/includes/class-vazirfont-gravityview-integration.php';

$integration = VazirFont_GravityView_Integration::get_instance();
vf_gv_assert( isset( $GLOBALS['vf_gv_actions']['enqueue_block_editor_assets'] ), 'adapter registers only on the block-editor asset lifecycle' );
vf_gv_assert( 999 === $GLOBALS['vf_gv_actions']['enqueue_block_editor_assets'][0][1], 'adapter attaches after host block assets have been registered' );

$host_handle = 'gk-gravityview-blocks-view-editor-style';
$block_name  = 'gk-gravityview-blocks/view';
$block       = new WP_Block_Type();
$block->editor_style_handles = array( $host_handle );
WP_Block_Type_Registry::get_instance()->register( $block_name, $block );
$GLOBALS['vf_gv_styles'][ $host_handle ] = array( 'registered' => true );

VazirFontPlugin::update_options(
	array(
		'enable_admin'         => true,
		'enable_gravity_forms' => true,
		'exclude_selectors'    => array( '.vazir-gv-evidence-excluded', '[data-vazir="::before"]', '[data-icon]:before' ),
	)
);
$integration->enqueue_editor_typography();
$css = implode( "\n", $GLOBALS['vf_gv_inline'][ $host_handle ] ?? array() );
vf_gv_assert( '' !== $css, 'repair attaches inline CSS to GravityView\'s registered View-block editor style' );
vf_gv_assert( false !== strpos( $css, '.gk-gravityview-blocks .view-selector [class$="-control"]' ), 'React Select repair uses the bounded GravityView control selector' );
vf_gv_assert( false === strpos( $css, '[class^="gk-select-"' ), 'repair does not depend on generated Emotion hash prefixes' );
vf_gv_assert( false === strpos( $css, 'input[role="combobox"]' ), 'already-correct React Select input is not directly repaired' );
vf_gv_assert( false !== strpos( $css, '.gk-gravityview-blocks .react-datepicker' ), 'Datepicker repair is rooted in the GravityView block scope' );
vf_gv_assert( false === strpos( $css, '.loading-placeholder' ), 'oEmbed placeholder remains outside this production repair' );
vf_gv_assert( false === strpos( $css, 'menuPortal' ) && false === strpos( $css, '[role="listbox"]' ), 'detached React Select portal remains outside this production repair' );
vf_gv_assert( false === strpos( $css, '!important' ), 'bounded selectors do not require !important at the qualified cascade points' );
$root_guard = ':not(:where(.vazir-gv-evidence-excluded, .vazir-gv-evidence-excluded *, [data-vazir="::before"], [data-vazir="::before"] *))';
$descendant_guard = ':not(:has(:where(.vazir-gv-evidence-excluded, [data-vazir="::before"])))';
vf_gv_assert( 2 === substr_count( $css, $root_guard ), 'both admitted normal-descendant selectors receive root/descendant exclusion protection' );
vf_gv_assert( 2 === substr_count( $css, $descendant_guard ), 'both admitted selectors receive descendant-containment exclusion protection' );
vf_gv_assert( false === strpos( $css, '[data-icon]:before' ), 'pseudo-element exclusions are not inserted into text enforcement guards' );
vf_gv_assert( 0 === preg_match( '/font-family\s*:\s*(?:inherit|initial|revert(?:-layer)?)\b/i', $css ), 'exclusions remain negative applicability boundaries' );

$control = '.gk-gravityview-blocks .view-selector [class$="-control"]';
$datepicker = '.gk-gravityview-blocks .react-datepicker';
foreach ( array( $control, $datepicker ) as $target ) {
	$qualified = (string) vf_gv_private_invoke( $integration, 'apply_exclusion_boundary', array( $target, array( '.gk-gravityview-blocks .no-vazir' ) ) );
	vf_gv_assert( false !== strpos( $qualified, ':not(:has(:where(.no-vazir)))' ), 'exact GravityView host-scope exclusion is safely relativized for ' . $target );
	$unsafe = (string) vf_gv_private_invoke( $integration, 'apply_exclusion_boundary', array( $target, array( '.container .no-vazir' ) ) );
	vf_gv_assert( '' === $unsafe, 'unsafe document-context exclusion fails the bounded repair closed for ' . $target );
}

vf_gv_reset( $integration );
VazirFontPlugin::update_options( array( 'exclude_selectors' => array( '.safe:has(.nested)' ) ) );
$integration->enqueue_editor_typography();
vf_gv_assert( array() === $GLOBALS['vf_gv_inline'], 'relational :has() exclusion fails the entire bounded GravityView repair closed' );

vf_gv_reset( $integration );
VazirFontPlugin::update_options( array( 'exclude_selectors' => array(), 'enable_admin' => false, 'enable_gravity_forms' => true ) );
$integration->enqueue_editor_typography();
vf_gv_assert( array() === $GLOBALS['vf_gv_inline'], 'existing admin typography toggle disables GravityView editor repair' );

vf_gv_reset( $integration );
VazirFontPlugin::update_options( array( 'enable_admin' => true, 'enable_gravity_forms' => false ) );
$integration->enqueue_editor_typography();
vf_gv_assert( array() === $GLOBALS['vf_gv_inline'], 'existing Gravity compatibility toggle disables GravityView editor repair' );

vf_gv_reset( $integration );
VazirFontPlugin::update_options( array( 'enable_admin' => true, 'enable_gravity_forms' => true ) );
WP_Block_Type_Registry::get_instance()->unregister( $block_name );
$integration->enqueue_editor_typography();
vf_gv_assert( array() === $GLOBALS['vf_gv_inline'], 'repair fails closed when the GravityView View block capability is absent' );

vf_gv_reset( $integration );
WP_Block_Type_Registry::get_instance()->register( $block_name, $block );
unset( $GLOBALS['vf_gv_styles'][ $host_handle ] );
$integration->enqueue_editor_typography();
vf_gv_assert( array() === $GLOBALS['vf_gv_inline'], 'repair fails closed when the expected GravityView editor style handle is not registered' );

fwrite( STDOUT, "ALL GRAVITYVIEW CONTRACT CHECKS PASSED\n" );
