<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}

$selector_boundary_file   = dirname( __DIR__ ) . '/includes/class-vazirfont-selector-boundary.php';
$selector_boundary_source = file_get_contents( $selector_boundary_file );

require $selector_boundary_file;
require dirname( __DIR__ ) . '/includes/class-vazirfont-gravityforms-integration.php';
require dirname( __DIR__ ) . '/includes/class-vazirfont-gravityflow-integration.php';
require dirname( __DIR__ ) . '/includes/class-vazirfont-gravityperks-integration.php';
require dirname( __DIR__ ) . '/includes/class-vazirfont-gravityview-integration.php';

function vf_descendant_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

/**
 * @param object $instance
 * @param mixed[] $arguments
 * @return mixed
 */
function vf_descendant_private_invoke( $instance, string $method_name, array $arguments = array() ) {
	$method = ( new ReflectionClass( $instance ) )->getMethod( $method_name );
	$method->setAccessible( true );
	return $method->invokeArgs( $instance, $arguments );
}

vf_descendant_assert( is_string( $selector_boundary_source ), 'Selector-boundary production source is readable for dependency falsification.' );
vf_descendant_assert(
	0 === preg_match( '/\bctype_[a-z0-9_]*\s*\(/i', (string) $selector_boundary_source ),
	'Selector-boundary production code has no ctype_* function dependency.'
);

$adapters = array(
	'Gravity Perks' => array(
		'instance' => ( new ReflectionClass( 'VazirFont_GravityPerks_Integration' ) )->newInstanceWithoutConstructor(),
		'target' => 'body.perk-iframe .perk-settings .description',
		'scope' => '.perk-settings',
		'scope_exclusion' => '.perk-settings .no-vazir',
		'scope_nested_exclusion' => '.perk-settings .description .no-vazir',
		'scope_child_exclusion' => '.perk-settings .description > .no-vazir',
		'invoke' => static function ( $instance, string $target, array $exclusions ): string {
			return (string) vf_descendant_private_invoke( $instance, 'apply_exclusion_boundary', array( $target, $exclusions ) );
		},
	),
	'Gravity Forms' => array(
		'instance' => ( new ReflectionClass( 'VazirFont_GravityForms_Integration' ) )->newInstanceWithoutConstructor(),
		'target' => '.gform_wrapper .gfield_description',
		'scope' => '.gform_wrapper',
		'scope_exclusion' => '.gform_wrapper .no-vazir',
		'scope_nested_exclusion' => '.gform_wrapper .gfield_description .no-vazir',
		'scope_child_exclusion' => '.gform_wrapper .gfield_description > .no-vazir',
		'invoke' => static function ( $instance, string $target, array $exclusions ): string {
			return (string) vf_descendant_private_invoke( $instance, 'apply_exclusion_boundary', array( $target, $exclusions, true ) );
		},
	),
	'Gravity Flow' => array(
		'instance' => ( new ReflectionClass( 'VazirFont_GravityFlow_Integration' ) )->newInstanceWithoutConstructor(),
		'target' => '.gflow-grid .ag-theme-alpine',
		'scope' => '.gflow-grid',
		'scope_exclusion' => '.gflow-grid .no-vazir',
		'scope_nested_exclusion' => '.gflow-grid .ag-theme-alpine .no-vazir',
		'scope_child_exclusion' => '.gflow-grid .ag-theme-alpine > .no-vazir',
		'invoke' => static function ( $instance, string $target, array $exclusions ): string {
			return (string) vf_descendant_private_invoke( $instance, 'apply_exclusion_boundary', array( $target, $exclusions, true ) );
		},
	),
	'GravityView' => array(
		'instance' => ( new ReflectionClass( 'VazirFont_GravityView_Integration' ) )->newInstanceWithoutConstructor(),
		'target' => '.gk-gravityview-blocks .react-datepicker',
		'scope' => '.gk-gravityview-blocks',
		'scope_exclusion' => '.gk-gravityview-blocks .no-vazir',
		'scope_nested_exclusion' => '.gk-gravityview-blocks .react-datepicker .no-vazir',
		'scope_child_exclusion' => '.gk-gravityview-blocks .react-datepicker > .no-vazir',
		'invoke' => static function ( $instance, string $target, array $exclusions ): string {
			return (string) vf_descendant_private_invoke( $instance, 'apply_exclusion_boundary', array( $target, $exclusions ) );
		},
	),
);

$safe_local_cases = array(
	'.no-vazir',
	'.no-vazir.special',
	'[data-vazir="::before"]',
	'[data-vazir="space > plus + sibling ~ text"]',
	'[data-token=":has(foo)"]',
	"[data-token=':has(foo)']",
	'.no-vazir:not(.inside > .functional + .argument)',
);
$unsafe_complex_cases = array(
	'.container .no-vazir',
	'.container > .no-vazir',
	'.anchor + .no-vazir',
	'.anchor ~ .no-vazir',
	'.safe:has(.nested)',
	'.safe:not(:has(.nested))',
);
$real_pseudo_cases = array( '[data-icon]:before', '.dashicons::before' );
$css_whitespace_cases = array(
	'space' => ' ',
	'tab' => "\t",
	'line-feed' => "\n",
	'carriage-return' => "\r",
	'form-feed' => "\f",
);

foreach ( $adapters as $label => $contract ) {
	$instance = $contract['instance'];
	$target = $contract['target'];
	$invoke = $contract['invoke'];

	foreach ( $safe_local_cases as $exclusion ) {
		$result = $invoke( $instance, $target, array( $exclusion ) );
		vf_descendant_assert( '' !== $result, $label . ' keeps a relative-safe exclusion representable: ' . $exclusion );
	}

	$scope_exclusion = $contract['scope_exclusion'];
	$scope_result = $invoke( $instance, $target, array( $scope_exclusion ) );
	vf_descendant_assert( '' !== $scope_result, $label . ' keeps its exact host-scope ancestor-qualified exclusion representable.' );
	vf_descendant_assert( false !== strpos( $scope_result, ':not(:where(' . $scope_exclusion . ', ' . $scope_exclusion . ' *))' ), $label . ' preserves document-context root/descendant negative applicability.' );
	vf_descendant_assert( false !== strpos( $scope_result, ':not(:has(:where(.no-vazir)))' ), $label . ' safely relativizes only the guaranteed host-scope ancestor for descendant containment.' );

	foreach ( $css_whitespace_cases as $whitespace_name => $whitespace ) {
		$whitespace_exclusion = $contract['scope'] . $whitespace . '.no-vazir';
		$result = $invoke( $instance, $target, array( $whitespace_exclusion ) );
		vf_descendant_assert( '' !== $result, $label . ' recognizes CSS ' . $whitespace_name . ' as the qualified descendant combinator.' );
		vf_descendant_assert( false !== strpos( $result, ':not(:has(:where(.no-vazir)))' ), $label . ' safely relativizes CSS ' . $whitespace_name . ' without Ctype.' );
	}

	foreach ( array( $contract['scope_nested_exclusion'], $contract['scope_child_exclusion'] ) as $scope_complex_exclusion ) {
		$result = $invoke( $instance, $target, array( $scope_complex_exclusion ) );
		vf_descendant_assert( '' === $result, $label . ' fails closed when stripping its host scope would still leave a top-level combinator: ' . $scope_complex_exclusion );
	}

	foreach ( $unsafe_complex_cases as $exclusion ) {
		$result = $invoke( $instance, $target, array( $exclusion ) );
		vf_descendant_assert( '' === $result, $label . ' fails the inheritable repair closed for unsafe relative reuse: ' . $exclusion );
	}

	foreach ( $real_pseudo_cases as $selector ) {
		vf_descendant_assert( true === vf_descendant_private_invoke( $instance, 'selector_targets_pseudo_element', array( $selector ) ), $label . ' preserves real pseudo-element classification: ' . $selector );
	}
	vf_descendant_assert( false === vf_descendant_private_invoke( $instance, 'selector_targets_pseudo_element', array( '[data-vazir="::before"]' ) ), $label . ' preserves quoted pseudo-looking attribute text as an element selector.' );
}

$forms = $adapters['Gravity Forms']['instance'];
$forms_preview = (string) vf_descendant_private_invoke( $forms, 'apply_exclusion_boundary', array( '#preview_hdr', array( '.gform_wrapper .no-vazir' ), true ) );
vf_descendant_assert( '' === $forms_preview, 'Gravity Forms does not pretend a gform_wrapper ancestor is guaranteed for Preview chrome.' );

$flow = $adapters['Gravity Flow']['instance'];
$flow_portal = (string) vf_descendant_private_invoke( $flow, 'apply_exclusion_boundary', array( '.flatpickr-calendar.ag-custom-component-popup', array( '.gflow-grid .no-vazir' ), true ) );
vf_descendant_assert( '' === $flow_portal, 'Gravity Flow does not pretend the detached Flatpickr portal is inside gflow-grid.' );

$gravityview = $adapters['GravityView']['instance'];
$gravityview_portal = (string) vf_descendant_private_invoke( $gravityview, 'apply_exclusion_boundary', array( '[role="listbox"]', array( '.gk-gravityview-blocks .no-vazir' ) ) );
vf_descendant_assert( '' === $gravityview_portal, 'GravityView does not pretend the detached React Select portal is inside its block scope.' );

fwrite( STDOUT, "ALL DESCENDANT EXCLUSION CONTRACT CHECKS PASSED\n" );
