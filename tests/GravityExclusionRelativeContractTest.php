<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GravityExclusionRelativeContractTest extends TestCase {
	private object $perks;
	private object $forms;
	private object $flow;

	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}
		require_once VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php';
		require_once VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityflow-integration.php';
		require_once VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityperks-integration.php';

		$this->perks = ( new ReflectionClass( 'VazirFont_GravityPerks_Integration' ) )->newInstanceWithoutConstructor();
		$this->forms = ( new ReflectionClass( 'VazirFont_GravityForms_Integration' ) )->newInstanceWithoutConstructor();
		$this->flow  = ( new ReflectionClass( 'VazirFont_GravityFlow_Integration' ) )->newInstanceWithoutConstructor();
	}

	/** @param mixed[] $arguments @return mixed */
	private function callPrivate( object $instance, string $methodName, array $arguments = array() ) {
		$method = ( new ReflectionClass( $instance ) )->getMethod( $methodName );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $arguments );
	}

	/** @return array<string,array{0:object,1:string,2:bool}> */
	private function inheritableAdapters(): array {
		return array(
			'Gravity Perks' => array( $this->perks, 'body.perk-iframe .perk-settings .description', false ),
			'Gravity Forms' => array( $this->forms, '.gform_wrapper .gfield_description', true ),
			'Gravity Flow'  => array( $this->flow, '.gflow-grid .ag-theme-alpine', true ),
		);
	}

	/** @param string[] $exclusions */
	private function applyBoundary( string $label, object $instance, string $target, bool $needsProtectFlag, array $exclusions ): string {
		$arguments = array( $target, $exclusions );
		if ( $needsProtectFlag ) {
			$arguments[] = true;
		}
		$result = $this->callPrivate( $instance, 'apply_exclusion_boundary', $arguments );
		$this->assertIsString( $result, $label . ' boundary must return a selector string.' );
		return $result;
	}

	public function test_relative_safe_local_exclusions_remain_supported(): void {
		$cases = array(
			'.no-vazir',
			'.no-vazir.special',
			'[data-vazir="::before"]',
			'[data-note="contains > + ~ spaces"]',
			'.no-vazir:not(.anchor + .other)',
		);
		foreach ( $this->inheritableAdapters() as $label => $adapter ) {
			list( $instance, $target, $needsProtectFlag ) = $adapter;
			foreach ( $cases as $selector ) {
				$this->assertTrue( (bool) $this->callPrivate( $instance, 'is_valid_css_selector', array( $selector ) ), $label . ' must continue to admit the selector grammar for ' . $selector );
				$result = $this->applyBoundary( $label, $instance, $target, $needsProtectFlag, array( $selector ) );
				$this->assertStringContainsString( ':not(:has(:where(' . $selector . ')))', $result, $label . ' must keep the relative-safe selector in descendant containment.' );
			}
		}
	}

	public function test_real_pseudo_elements_remain_positive_controls(): void {
		foreach ( array( '[data-icon]:before', '.dashicons::before' ) as $selector ) {
			foreach ( $this->inheritableAdapters() as $label => $adapter ) {
				$this->assertTrue( (bool) $this->callPrivate( $adapter[0], 'selector_targets_pseudo_element', array( $selector ) ), $label . ' must keep classifying a true pseudo-element selector: ' . $selector );
			}
		}
	}

	public function test_known_scope_ancestor_qualifiers_are_exactly_relativized(): void {
		$cases = array(
			'Gravity Perks' => array( $this->perks, 'body.perk-iframe .perk-settings .description', false, '.perk-settings .no-vazir' ),
			'Gravity Forms' => array( $this->forms, '.gform_wrapper .gfield_description', true, '.gform_wrapper .no-vazir' ),
			'Gravity Flow'  => array( $this->flow, '.gflow-grid .ag-theme-alpine', true, '.gflow-grid .no-vazir' ),
		);
		foreach ( $cases as $label => $case ) {
			list( $instance, $target, $needsProtectFlag, $exclusion ) = $case;
			$result = $this->applyBoundary( $label, $instance, $target, $needsProtectFlag, array( $exclusion ) );
			$this->assertStringContainsString( ':not(:where(' . $exclusion . ', ' . $exclusion . ' *))', $result, $label . ' must preserve the full document-context root/descendant boundary.' );
			$this->assertStringContainsString( ':not(:has(:where(.no-vazir)))', $result, $label . ' must evaluate descendant containment with the exact local predicate.' );
			$this->assertStringNotContainsString( ':not(:has(:where(' . $exclusion . ')))', $result, $label . ' must not reuse the ancestor-qualified selector unsafely inside :has().' );
		}
	}

	public function test_unrepresentable_top_level_combinators_fail_inheritable_rules_closed(): void {
		$cases = array( '.unknown-scope .no-vazir', '.container > .no-vazir', '.anchor + .no-vazir', '.anchor ~ .no-vazir', '.safe:has(.nested)' );
		foreach ( $this->inheritableAdapters() as $label => $adapter ) {
			list( $instance, $target, $needsProtectFlag ) = $adapter;
			foreach ( $cases as $selector ) {
				$this->assertTrue( (bool) $this->callPrivate( $instance, 'is_valid_css_selector', array( $selector ) ), $label . ' test case must stay inside the admitted selector grammar: ' . $selector );
				$this->assertSame( '', $this->applyBoundary( $label, $instance, $target, $needsProtectFlag, array( $selector ) ), $label . ' must fail the inheritable repair closed for ' . $selector );
			}
		}
	}

	public function test_known_scope_relativization_is_not_applied_outside_that_scope(): void {
		$this->assertSame( '', $this->applyBoundary( 'Gravity Forms Preview', $this->forms, '#preview_hdr', true, array( '.gform_wrapper .no-vazir' ) ), 'Gravity Forms Preview chrome must not receive a gform_wrapper-relative approximation.' );
		$this->assertSame( '', $this->applyBoundary( 'Gravity Flow portal', $this->flow, '.flatpickr-calendar.ag-custom-component-popup', true, array( '.gflow-grid .no-vazir' ) ), 'Gravity Flow detached portal must not receive a gflow-grid-relative approximation.' );
	}
}
