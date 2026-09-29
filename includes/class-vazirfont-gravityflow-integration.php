<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Flow 3.1.0 typography compatibility adapter.
 *
 * Gravity Flow remains authoritative for Inbox behavior and AG Grid lifecycle.
 * This adapter only restores Vazirmatn on the bounded text surfaces whose host
 * CSS explicitly replaces normal inheritance, using the product's supported
 * enqueue hooks after its own styles have been registered/enqueued.
 */
final class VazirFont_GravityFlow_Integration {
	private const SUPPORTED_VERSION   = '3.1.0';
	private const STYLE_HANDLE        = 'vazir-font-gravity-flow';
	private const ADMIN_DEPENDENCY    = 'gravityflow_admin_css';
	private const FRONTEND_DEPENDENCY = 'gravityflow_theme_css';

	private static ?self $instance = null;
	private bool $flow_available = false;
	private ?string $cached_css = null;
	private array $inline_handles = array();

	private function __construct() {
		$this->flow_available = $this->is_supported_gravity_flow_runtime();
		if ( $this->flow_available ) {
			$this->init_hooks();
		}
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_GravityFlow_Integration singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function is_supported_gravity_flow_runtime(): bool {
		return class_exists( 'Gravity_Flow' )
			&& defined( 'GRAVITY_FLOW_VERSION' )
			&& self::SUPPORTED_VERSION === (string) GRAVITY_FLOW_VERSION;
	}

	private function init_hooks(): void {
		add_action( 'gravityflow_enqueue_admin_scripts', array( $this, 'enqueue_admin_assets' ), 999 );
		add_action( 'gravityflow_enqueue_frontend_scripts', array( $this, 'enqueue_frontend_assets' ), 999 );
	}

	public function enqueue_admin_assets(): void {
		$this->enqueue_style( 'admin', self::ADMIN_DEPENDENCY );
	}

	public function enqueue_frontend_assets(): void {
		$this->enqueue_style( 'frontend', self::FRONTEND_DEPENDENCY );
	}

	private function enqueue_style( string $context, string $dependency ): void {
		if ( ! $this->is_enabled( $context ) ) {
			return;
		}

		$handle = self::STYLE_HANDLE . '-' . $context;
		wp_register_style( $handle, false, array( $dependency ), VAZIR_FONT_VERSION );
		wp_enqueue_style( $handle );

		if ( isset( $this->inline_handles[ $handle ] ) ) {
			return;
		}

		$css = $this->get_gravityflow_css();
		if ( '' !== trim( $css ) ) {
			wp_add_inline_style( $handle, $css );
		}
		$this->inline_handles[ $handle ] = true;
	}

	private function is_enabled( string $context ): bool {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return false;
		}

		if ( 'admin' === $context ) {
			return ! empty( $options['enable_admin'] );
		}

		if ( 'frontend' === $context ) {
			return ! empty( $options['enable_frontend'] );
		}

		return false;
	}

	private function get_gravityflow_css(): string {
		if ( null !== $this->cached_css ) {
			return $this->cached_css;
		}

		$family = apply_filters(
			'vazir_font_family',
			"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);
		$negative_exclusions = $this->get_negative_scope_selectors();
		$css                 = '';

		// Gravity Flow 3.1.0 declares a system stack directly on this AG Grid
		// theme root. Guard the inheritable correction against any excluded
		// descendant so exclusions cannot be crossed through inheritance.
		$grid_root = $this->apply_exclusion_boundary(
			'.gflow-grid .ag-theme-alpine',
			$negative_exclusions,
			true
		);
		if ( '' !== $grid_root ) {
			$css .= "{$grid_root} {\n\tfont-family: {$family};\n}\n";
		}

		// Gravity Flow also declares the system stack directly on material AG
		// Grid filter/date inputs. Override only those exact text controls; icon
		// and glyph nodes are intentionally not part of this selector set.
		$control_selectors = $this->build_enforcement_selector_list(
			array(
				'.gflow-grid .ag-theme-alpine .ag-input-wrapper.custom-date-filter input',
				'.gflow-grid .ag-theme-alpine input[class^="ag-"]',
				'.gflow-grid .ag-theme-alpine textarea[class^="ag-"]',
			),
			$negative_exclusions
		);
		if ( '' !== $control_selectors ) {
			$css .= "\n{$control_selectors} {\n\tfont-family: {$family} !important;\n}\n";
		}

		// Gravity Flow's custom date component appends the Flatpickr popup under
		// body. The portal therefore needs both its own exclusion boundary and a
		// source-input guard; otherwise an Inbox excluded by an ancestor could
		// still leak Vazirmatn into the detached calendar.
		$date_picker = $this->apply_portal_exclusion_boundary(
			'.flatpickr-calendar.ag-custom-component-popup',
			'.gflow-grid .ag-theme-alpine .ag-input-wrapper.custom-date-filter input',
			$negative_exclusions
		);
		if ( '' !== $date_picker ) {
			$css .= "\n{$date_picker} {\n\tfont-family: {$family};\n}\n";
		}

		$this->cached_css = $css;
		return $css;
	}

	/**
	 * Consume the existing vazir_font_options['exclude_selectors'] authority.
	 * Pseudo-elements remain owned by the dedicated icon/glyph protections and
	 * are not forced into relational element guards.
	 *
	 * @return string[]
	 */
	private function get_negative_scope_selectors(): array {
		$options = VazirFontPlugin::get_options();
		$exclude = $options['exclude_selectors'] ?? array();
		if ( ! is_array( $exclude ) ) {
			return array();
		}

		$valid = array();
		foreach ( $exclude as $selector ) {
			$sanitized = $this->sanitize_css_selector( (string) $selector );
			if ( '' === $sanitized || ! $this->is_valid_css_selector( $sanitized ) ) {
				continue;
			}

			foreach ( $this->split_top_level_selector_list( $sanitized ) as $component ) {
				if ( '' === $component || ! $this->is_valid_css_selector( $component ) ) {
					continue;
				}
				if ( $this->selector_targets_pseudo_element( $component ) ) {
					continue;
				}
				$valid[] = $component;
			}
		}

		return array_values( array_unique( $valid ) );
	}

	/**
	 * Apply root/descendant negative scope. Inheritable Flow rules also reject
	 * targets containing an excluded subtree so inheritance cannot cross the
	 * Owner-configured boundary. Nested :has() cannot be represented safely in
	 * the required guard, so such cases fail closed by omitting the rule.
	 *
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function apply_exclusion_boundary( string $selector, array $exclude_selectors, bool $protect_descendants = false ): string {
		if ( array() === $exclude_selectors ) {
			return $selector;
		}

		$blocked_selectors = $this->build_blocked_selector_list( $exclude_selectors );
		$guarded           = $selector . ':not(:where(' . implode( ', ', $blocked_selectors ) . '))';
		if ( ! $protect_descendants ) {
			return $guarded;
		}

		if ( $this->contains_relational_exclusion( $exclude_selectors ) ) {
			return '';
		}

		return $guarded . ':not(:has(:where(' . implode( ', ', $exclude_selectors ) . ')))';
	}

	/**
	 * Apply exclusion semantics to a host-owned portal that is detached from
	 * the Flow subtree. If its source input is inside an excluded root, suppress
	 * the calendar correction for the whole document rather than leak typography
	 * across the configured boundary. Multiple grids are intentionally handled
	 * conservatively: one excluded date-filter source makes the portal rule fail
	 * closed for that document.
	 *
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function apply_portal_exclusion_boundary( string $selector, string $source_selector, array $exclude_selectors ): string {
		$guarded = $this->apply_exclusion_boundary( $selector, $exclude_selectors, true );
		if ( '' === $guarded || array() === $exclude_selectors ) {
			return $guarded;
		}

		if ( $this->contains_relational_exclusion( $exclude_selectors ) ) {
			return '';
		}

		$blocked_selectors = $this->build_blocked_selector_list( $exclude_selectors );
		$source_guard      = 'body:not(:has(' . $source_selector . ':where(' . implode( ', ', $blocked_selectors ) . ')))';

		return $source_guard . ' ' . $guarded;
	}

	/**
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 * @return string[]
	 */
	private function build_blocked_selector_list( array $exclude_selectors ): array {
		$blocked_selectors = array();
		foreach ( $exclude_selectors as $exclude_selector ) {
			$blocked_selectors[] = $exclude_selector;
			$blocked_selectors[] = $exclude_selector . ' *';
		}
		return $blocked_selectors;
	}

	/**
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function contains_relational_exclusion( array $exclude_selectors ): bool {
		foreach ( $exclude_selectors as $exclude_selector ) {
			if ( false !== stripos( $exclude_selector, ':has(' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string[] $selectors Internal Gravity Flow enforcement selectors.
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function build_enforcement_selector_list( array $selectors, array $exclude_selectors ): string {
		$guarded = array();
		foreach ( $selectors as $selector ) {
			$candidate = $this->apply_exclusion_boundary( $selector, $exclude_selectors );
			if ( '' !== $candidate ) {
				$guarded[] = $candidate;
			}
		}
		return implode( ",\n", $guarded );
	}

	/**
	 * @return string[]
	 */
	private function split_top_level_selector_list( string $selector ): array {
		$parts         = array();
		$buffer        = '';
		$paren_depth   = 0;
		$bracket_depth = 0;
		$quote         = '';
		$length        = strlen( $selector );

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $selector[ $index ];

			if ( '' !== $quote ) {
				$buffer .= $char;
				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote   = $char;
				$buffer .= $char;
				continue;
			}

			if ( '(' === $char ) {
				$paren_depth++;
			} elseif ( ')' === $char && $paren_depth > 0 ) {
				$paren_depth--;
			} elseif ( '[' === $char ) {
				$bracket_depth++;
			} elseif ( ']' === $char && $bracket_depth > 0 ) {
				$bracket_depth--;
			}

			if ( ',' === $char && 0 === $paren_depth && 0 === $bracket_depth ) {
				$part = trim( $buffer );
				if ( '' !== $part ) {
					$parts[] = $part;
				}
				$buffer = '';
				continue;
			}

			$buffer .= $char;
		}

		$part = trim( $buffer );
		if ( '' !== $part ) {
			$parts[] = $part;
		}

		return $parts;
	}

	private function selector_targets_pseudo_element( string $selector ): bool {
		return 1 === preg_match( '/::[a-zA-Z0-9_-]+|:(?:before|after|first-letter|first-line)\b/i', $selector );
	}

	private function sanitize_css_selector( string $selector ): string {
		$selector = str_ireplace( array( '@import', 'url(' ), '', $selector );
		$selector = (string) preg_replace( '/\/\*.*?\*\//', '', $selector );
		$selector = str_replace( array( '{', '}', ';' ), ' ', $selector );
		$selector = (string) preg_replace( '/[^a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~=\"\'\^$|]/', '', $selector );
		$selector = trim( (string) preg_replace( '/\s+/', ' ', $selector ) );
		return strlen( $selector ) > 200 ? substr( $selector, 0, 200 ) : $selector;
	}

	private function is_valid_css_selector( string $selector ): bool {
		if ( '' === $selector || false !== strpos( $selector, '{' ) || false !== strpos( $selector, '}' ) || false !== strpos( $selector, ';' ) || false !== strpos( $selector, '/*' ) ) {
			return false;
		}
		if ( ! preg_match( '/^[a-zA-Z.#\[]/', $selector ) ) {
			return false;
		}
		return 1 === preg_match( '/^[a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~=\"\'\^$|]+$/', $selector );
	}
}
