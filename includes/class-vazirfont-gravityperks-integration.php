<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Perks standalone Settings typography compatibility adapter.
 *
 * Gravity Perks remains authoritative for routing, Settings rendering, form
 * controls, saving, notices, scripts, and host styles. This adapter only
 * attaches bundled Vazirmatn font faces plus narrowly scoped typography rules
 * to Gravity Perks' already-registered gwp-admin handle at WordPress' supported
 * print_styles_array boundary.
 */
final class VazirFont_GravityPerks_Integration {
	private const HOST_STYLE_HANDLE = 'gwp-admin';

	private static ?self $instance = null;
	private bool $perks_available = false;
	private bool $inline_attached = false;
	private ?string $cached_css = null;

	private function __construct() {
		$this->perks_available = $this->is_gravity_perks_runtime_available();
		if ( $this->perks_available ) {
			add_filter( 'print_styles_array', array( $this, 'filter_print_styles_array' ), 999 );
		}
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_GravityPerks_Integration singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function is_gravity_perks_runtime_available(): bool {
		return class_exists( 'GravityPerks' );
	}

	/**
	 * Attach the standalone Settings correction without changing WordPress' style
	 * dependency/to-do list.
	 *
	 * @param string[] $handles Styles selected by WP_Styles for this print pass.
	 * @return string[] Unchanged style handle list.
	 */
	public function filter_print_styles_array( array $handles ): array {
		if ( $this->inline_attached || ! $this->is_enabled() || ! $this->is_standalone_settings_request( $handles ) ) {
			return $handles;
		}

		$css = $this->get_settings_css();
		if ( '' === trim( $css ) ) {
			return $handles;
		}

		if ( wp_add_inline_style( self::HOST_STYLE_HANDLE, $css ) ) {
			$this->inline_attached = true;
		}

		return $handles;
	}

	private function is_enabled(): bool {
		$options = VazirFontPlugin::get_options();
		return ! empty( $options['enable_admin'] ) && ! empty( $options['enable_gravity_forms'] );
	}

	/**
	 * @param string[] $handles Styles selected by WP_Styles for this print pass.
	 */
	private function is_standalone_settings_request( array $handles ): bool {
		if ( ! is_admin() || ! $this->perks_available || ! class_exists( 'GWPerksPage' ) || ! method_exists( 'GWPerksPage', 'load_perk_settings' ) ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : '';
		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['slug'] ) ) : '';

		if ( 'gwp_perks' !== $page || '' === $view || '' === $slug ) {
			return false;
		}

		if ( ! in_array( self::HOST_STYLE_HANDLE, $handles, true ) ) {
			return false;
		}

		return wp_style_is( self::HOST_STYLE_HANDLE, 'registered' );
	}

	private function get_settings_css(): string {
		if ( null !== $this->cached_css ) {
			return $this->cached_css;
		}

		$negative_exclusions = $this->get_negative_scope_selectors();
		if ( null === $negative_exclusions ) {
			$this->cached_css = '';
			return $this->cached_css;
		}

		$selectors = $this->build_enforcement_selector_list(
			array(
				'body.perk-iframe .perk-settings .page-title',
				'body.perk-iframe .perk-settings label',
				'body.perk-iframe .perk-settings .description',
				'body.perk-iframe .perk-settings input[type="text"]',
				'body.perk-iframe .perk-settings input[type="email"]',
				'body.perk-iframe .perk-settings input[type="url"]',
				'body.perk-iframe .perk-settings input[type="search"]',
				'body.perk-iframe .perk-settings input[type="tel"]',
				'body.perk-iframe .perk-settings input[type="number"]',
				'body.perk-iframe .perk-settings input[type="password"]',
				'body.perk-iframe .perk-settings select',
				'body.perk-iframe .perk-settings textarea',
				'body.perk-iframe .perk-settings #gwp_save_settings',
			),
			$negative_exclusions
		);
		if ( '' === $selectors ) {
			$this->cached_css = '';
			return $this->cached_css;
		}

		$loader = VazirFont_Loader::get_instance();
		$family = (string) apply_filters(
			'vazir_font_family',
			"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);

		$this->cached_css = trim( $loader->get_font_face_css() )
			. "\n\n{$selectors} {\n\tfont-family: {$family} !important;\n}\n";
		return $this->cached_css;
	}

	/**
	 * Convert the existing exclude_selectors setting into safe element-level
	 * negative applicability boundaries. Pseudo-elements remain owned by host
	 * icon/glyph CSS. Any unrepresentable element selector fails the whole local
	 * repair closed rather than approximating the user's exclusion intent.
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
	 * Build the shared inheritable Settings selector list. Any selector that
	 * cannot receive the complete exclusion boundary fails the whole bounded
	 * repair closed rather than leaving a partially protected rule set.
	 *
	 * @param string[] $selectors Internal bounded Settings selectors.
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function build_enforcement_selector_list( array $selectors, array $exclude_selectors ): string {
		$guarded = array();
		foreach ( $selectors as $selector ) {
			$candidate = $this->apply_exclusion_boundary( $selector, $exclude_selectors );
			if ( '' === $candidate ) {
				return '';
			}
			$guarded[] = $candidate;
		}
		return implode( ",\n", $guarded );
	}

	/**
	 * Apply the single configured exclusion authority to inheritable font-family
	 * enforcement. The target itself and descendants of an excluded root are
	 * blocked, and a target containing an excluded subtree is also blocked so the
	 * descendant cannot inherit Vazirmatn from that ancestor.
	 *
	 * Descendant containment only embeds selector components whose document
	 * context can be represented safely relative to the current Settings target.
	 * Pseudo-element exclusions have already been removed from this element-level
	 * guard set; unrepresentable complex selectors fail the bounded repair closed.
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
			'.perk-settings',
			'body.perk-iframe .perk-settings'
		);
		if ( null === $descendant_exclusions ) {
			return '';
		}

		return $guarded . ':not(:has(:where(' . implode( ', ', $descendant_exclusions ) . ')))';
	}
}
