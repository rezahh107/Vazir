<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GravityView block-editor typography compatibility adapter.
 *
 * GravityView remains authoritative for block registration, editor controls,
 * React Select/Datepicker behavior, scripts, and host styles. This adapter only
 * attaches bounded font-family corrections to GravityView's registered View
 * block editor-style handle when the exact host capability is present.
 */
final class VazirFont_GravityView_Integration {
	private const BLOCK_NAME        = 'gk-gravityview-blocks/view';
	private const HOST_STYLE_HANDLE = 'gk-gravityview-blocks-view-editor-style';

	private static ?self $instance = null;
	private bool $gravityview_available = false;
	private bool $inline_attached = false;
	private ?string $cached_css = null;

	private function __construct() {
		$this->gravityview_available = $this->is_gravityview_runtime_available();
		if ( $this->gravityview_available ) {
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_typography' ), 999 );
		}
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_GravityView_Integration singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function is_gravityview_runtime_available(): bool {
		return defined( 'GRAVITYVIEW_FILE' );
	}

	/**
	 * Attach the correction to GravityView's own View-block editor stylesheet.
	 */
	public function enqueue_editor_typography(): void {
		if ( $this->inline_attached || ! $this->is_enabled() || ! $this->has_view_block_editor_style_capability() ) {
			return;
		}

		$css = $this->get_editor_css();
		if ( '' === trim( $css ) ) {
			return;
		}

		if ( wp_add_inline_style( self::HOST_STYLE_HANDLE, $css ) ) {
			$this->inline_attached = true;
		}
	}

	private function is_enabled(): bool {
		$options = VazirFontPlugin::get_options();
		return ! empty( $options['enable_admin'] ) && ! empty( $options['enable_gravity_forms'] );
	}

	private function has_view_block_editor_style_capability(): bool {
		if ( ! is_admin() || ! $this->gravityview_available || ! class_exists( 'WP_Block_Type_Registry' ) || ! class_exists( 'WP_Block_Type' ) ) {
			return false;
		}

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );
		if ( ! $block_type instanceof WP_Block_Type ) {
			return false;
		}

		if ( ! in_array( self::HOST_STYLE_HANDLE, (array) $block_type->editor_style_handles, true ) ) {
			return false;
		}

		return wp_style_is( self::HOST_STYLE_HANDLE, 'registered' );
	}

	private function get_editor_css(): string {
		if ( null !== $this->cached_css ) {
			return $this->cached_css;
		}

		$negative_exclusions = $this->get_negative_scope_selectors();
		if ( null === $negative_exclusions ) {
			$this->cached_css = '';
			return $this->cached_css;
		}

		$family = (string) apply_filters(
			'vazir_font_family',
			"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);

		$rules = array();

		// GravityView 3.3.4's own DocumentAwareSelect locates the real control by
		// the semantic react-select "-control" suffix. Reuse that semantic suffix
		// without depending on the generated Emotion hash prefix. A direct family
		// on the control is sufficient for its value text to inherit Vazirmatn; the
		// already-correct combobox input is intentionally untouched.
		$react_select_control = $this->apply_exclusion_boundary(
			'.gk-gravityview-blocks .view-selector [class$="-control"]',
			$negative_exclusions
		);
		if ( '' !== $react_select_control ) {
			$rules[] = "{$react_select_control} {\n\tfont-family: {$family};\n}";
		}

		// The qualified View-block Datepicker is a normal descendant of the
		// GravityView inspector. Its root owns the explicit Helvetica/Arial family;
		// one scoped root rule restores inheritance for month/day text while the
		// associated input and unrelated Datepickers remain untouched.
		$date_picker = $this->apply_exclusion_boundary(
			'.gk-gravityview-blocks .react-datepicker',
			$negative_exclusions
		);
		if ( '' !== $date_picker ) {
			$rules[] = "{$date_picker} {\n\tfont-family: {$family};\n}";
		}

		$this->cached_css = implode( "\n\n", $rules );
		return $this->cached_css;
	}

	/**
	 * Consume vazir_font_options['exclude_selectors'] as the single exclusion
	 * authority. Pseudo-elements remain owned by host icon/glyph CSS.
	 *
	 * @return string[]|null
	 */
	private function get_negative_scope_selectors(): ?array {
		$options = VazirFontPlugin::get_options();
		$exclude = $options['exclude_selectors'] ?? array();
		if ( ! is_array( $exclude ) ) {
			return null;
		}

		$valid = array();
		foreach ( $exclude as $selector ) {
			$selector = trim( (string) preg_replace( '/\s+/', ' ', (string) $selector ) );
			if ( '' === $selector ) {
				continue;
			}
			if ( ! $this->is_valid_css_selector( $selector ) ) {
				return null;
			}

			foreach ( $this->split_top_level_selector_list( $selector ) as $component ) {
				if ( '' === $component || ! $this->is_valid_css_selector( $component ) ) {
					return null;
				}
				if ( $this->selector_targets_pseudo_element( $component ) ) {
					continue;
				}
				$valid[] = $component;
			}
		}

		return array_values( array_unique( $valid ) );
	}

	/** @return string[] */
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
		$quote         = '';
		$bracket_depth = 0;
		$length        = strlen( $selector );

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $selector[ $index ];
			if ( '' !== $quote ) {
				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				continue;
			}
			if ( '[' === $char ) {
				$bracket_depth++;
				continue;
			}
			if ( ']' === $char && $bracket_depth > 0 ) {
				$bracket_depth--;
				continue;
			}
			if ( 0 !== $bracket_depth || ':' !== $char ) {
				continue;
			}
			if ( 1 === preg_match( '/^(?:::[a-zA-Z0-9_-]+|:(?:before|after|first-letter|first-line)\b)/i', substr( $selector, $index ) ) ) {
				return true;
			}
		}
		return false;
	}

	private function is_valid_css_selector( string $selector ): bool {
		if ( '' === $selector || strlen( $selector ) > 200 ) {
			return false;
		}
		if ( false !== stripos( $selector, '@import' ) || false !== stripos( $selector, 'url(' ) ) {
			return false;
		}
		if ( false !== strpos( $selector, '{' ) || false !== strpos( $selector, '}' ) || false !== strpos( $selector, ';' ) || false !== strpos( $selector, '/*' ) || false !== strpos( $selector, '*/' ) ) {
			return false;
		}
		if ( ! preg_match( '/^[a-zA-Z.#\[]/', $selector ) ) {
			return false;
		}
		if ( ! preg_match( '/^[a-zA-Z0-9\s\-_\.#:\*\[\]\(\),>+~="\'\^\$\|]+$/', $selector ) ) {
			return false;
		}
		if ( substr_count( $selector, '[' ) !== substr_count( $selector, ']' ) || substr_count( $selector, '(' ) !== substr_count( $selector, ')' ) ) {
			return false;
		}
		if ( 0 !== substr_count( $selector, '"' ) % 2 || 0 !== substr_count( $selector, "'" ) % 2 ) {
			return false;
		}
		foreach ( array( '##', '..', ',,', '>>', '++', '~~', '**' ) as $invalid_sequence ) {
			if ( false !== strpos( $selector, $invalid_sequence ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Apply root/descendant negative applicability plus descendant containment.
	 * Any exclusion that cannot be represented relative to the guaranteed
	 * GravityView block scope fails this bounded repair closed.
	 *
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function apply_exclusion_boundary( string $selector, array $exclude_selectors ): string {
		if ( array() === $exclude_selectors ) {
			return $selector;
		}

		$blocked = array();
		foreach ( $exclude_selectors as $exclude_selector ) {
			$blocked[] = $exclude_selector;
			$blocked[] = $exclude_selector . ' *';
		}
		$guarded = $selector . ':not(:where(' . implode( ', ', $blocked ) . '))';

		$descendant_exclusions = VazirFont_Selector_Boundary::for_descendant_containment(
			$selector,
			$exclude_selectors,
			'.gk-gravityview-blocks',
			'.gk-gravityview-blocks'
		);
		if ( null === $descendant_exclusions ) {
			return '';
		}

		return $guarded . ':not(:has(:where(' . implode( ', ', $descendant_exclusions ) . ')))';
	}
}
