<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Forms typography compatibility adapter.
 *
 * Native/current APIs are used for stylesheet delivery. Field-level class and
 * inline-font cleanup are retained as compatibility mechanisms until browser
 * characterization proves they can be removed without rendering regressions.
 */
final class VazirFont_GravityForms_Integration {
	private const STYLE_HANDLE = 'vazir-font-gravity-forms';
	private const ADMIN_STYLE_HANDLE = 'vazir-font-admin-runtime';

	private static ?self $instance = null;
	private bool $gf_available = false;
	private ?string $cached_css = null;
	private bool $style_registered = false;
	private bool $inline_attached = false;

	private function __construct() {
		$this->gf_available = $this->is_gravity_forms_active();
		if ( $this->gf_available ) {
			$this->init_hooks();
		}
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_GravityForms_Integration singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function is_gravity_forms_active(): bool {
		return class_exists( 'GFForms' ) && class_exists( 'GFCommon' );
	}

	private function init_hooks(): void {
		add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_gravityforms_assets' ], 999, 2 );
		add_action( 'gform_preview_init', [ $this, 'mark_preview_request' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_gravityforms_admin_assets' ], 20 );

		add_filter( 'gform_preview_styles', [ $this, 'filter_preview_styles' ], 10, 2 );
		add_filter( 'gform_noconflict_styles', [ $this, 'add_noconflict_styles' ] );

		// Compatibility mechanisms retained pending visual/computed-style proof.
		add_filter( 'gform_field_css_class', [ $this, 'add_field_css_class' ], 10, 3 );
		add_filter( 'gform_field_content', [ $this, 'remove_inline_font_styles' ], 999, 5 );
	}

	public function enqueue_gravityforms_assets( $form = [], $is_ajax = false ): void {
		unset( $form, $is_ajax );
		if ( ! $this->is_enabled() ) {
			return;
		}

		VazirFont_Loader::get_instance()->mark_gravityforms_request();
		$this->enqueue_style();
	}

	public function mark_preview_request(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}
		VazirFont_Loader::get_instance()->mark_gravityforms_request();
	}

	public function enqueue_gravityforms_admin_assets(): void {
		if ( ! $this->is_enabled() || ! $this->is_gravity_forms_admin_screen() ) {
			return;
		}
		VazirFont_Loader::get_instance()->mark_gravityforms_request();
		$this->enqueue_style();
	}

	/**
	 * Gravity Forms expects WordPress style handles, not raw CSS strings.
	 *
	 * @param mixed[] $styles Existing preview style handles.
	 * @param mixed[] $form Current form.
	 * @return mixed[]
	 */
	public function filter_preview_styles( array $styles, array $form = [] ): array {
		unset( $form );
		if ( ! $this->is_enabled() ) {
			return $styles;
		}

		VazirFont_Loader::get_instance()->mark_gravityforms_request();
		$this->enqueue_style();
		$styles[] = self::STYLE_HANDLE;
		return array_values( array_unique( $styles ) );
	}

	/**
	 * Allowlist plugin typography handles in Gravity Forms No Conflict Mode.
	 *
	 * The Gravity Forms handle provides form-specific typography. When general
	 * admin typography is enabled, the wp-admin runtime handle is also retained
	 * so No Conflict Mode cannot remove typography from the Form Editor UI.
	 *
	 * @param string[] $styles Existing allowed handles.
	 * @return string[]
	 */
	public function add_noconflict_styles( array $styles ): array {
		if ( ! $this->is_enabled() ) {
			return $styles;
		}

		$this->register_style();
		$styles[] = self::STYLE_HANDLE;

		$options = VazirFontPlugin::get_options();
		if ( ! empty( $options['enable_admin'] ) ) {
			VazirFont_Loader::get_instance()->enqueue_admin_fonts();
			$styles[] = self::ADMIN_STYLE_HANDLE;
		}

		return array_values( array_unique( $styles ) );
	}

	/**
	 * Retained compatibility class. It is intentionally not relied upon by the
	 * native Theme Framework path, but removing it is deferred until external
	 * and visual characterization demonstrates it is unnecessary.
	 */
	public function add_field_css_class( string $css_class, $field, array $form ): string {
		unset( $field, $form );
		if ( $this->is_enabled() ) {
			$css_class .= ' vazir-font-enabled-field';
		}
		return trim( $css_class );
	}

	/**
	 * Compatibility workaround retained pending licensed visual proof.
	 *
	 * Arbitrary configured CSS selectors cannot be truthfully matched against a
	 * field-content fragment here without implementing a second, incomplete DOM/
	 * selector engine. When any accepted element-level exclusion exists, fail
	 * closed and preserve inline font-family declarations. Cleanup remains active
	 * only when no element-level exclusion boundary needs to be honored.
	 */
	public function remove_inline_font_styles( string $content, $field, $value, $entry_id, $form_id ): string {
		unset( $field, $value, $entry_id, $form_id );
		if ( ! $this->is_enabled() || [] !== $this->get_negative_scope_selectors() ) {
			return $content;
		}

		$filtered = preg_replace(
			'/style=(["\'])(.*?)font-family\s*:[^;"\'>]*;?(.*?)\1/i',
			'style=$1$2$3$1',
			$content
		);
		return is_string( $filtered ) ? $filtered : $content;
	}

	private function enqueue_style(): void {
		$this->register_style();
		wp_enqueue_style( self::STYLE_HANDLE );

		if ( ! $this->inline_attached ) {
			wp_add_inline_style( self::STYLE_HANDLE, $this->get_gravityforms_css() );
			$this->inline_attached = true;
		}
	}

	private function register_style(): void {
		if ( $this->style_registered ) {
			return;
		}

		wp_register_style( self::STYLE_HANDLE, false, [], VAZIR_FONT_VERSION );
		$this->style_registered = true;
	}

	private function get_gravityforms_css(): string {
		if ( null !== $this->cached_css ) {
			return $this->cached_css;
		}

		if ( ! $this->is_enabled() ) {
			$this->cached_css = '';
			return '';
		}

		$family = apply_filters(
			'vazir_font_family',
			"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);
		$negative_exclusions = $this->get_negative_scope_selectors();

		$css = VazirFont_Loader::get_instance()->get_font_face_css();

		// Theme Framework CSS API remains the preferred current path. Because the
		// custom property is inherited, suppress this ancestor rule when it is an
		// excluded root/descendant or contains an excluded subtree. Include the
		// canonical wrapper class so the intended font override remains more
		// specific than Gravity Forms' framework default regardless of asset order.
		$framework_selector = $this->apply_exclusion_boundary(
			'.gform_wrapper.gform-theme--framework',
			$negative_exclusions,
			true
		);
		if ( '' !== $framework_selector ) {
			$css .= "\n{$framework_selector} {\n\t--gf-font-family-base: {$family};\n}\n";
		}

		// Current/legacy compatibility layer retained until licensed browser proof
		// permits narrower selectors or removal of !important. Every inheritable
		// font-family rule is guarded against excluded roots, descendants, and
		// containing an excluded subtree so it cannot leak Vazir into exclusions.
		$selectors = $this->build_enforcement_selector_list(
			[
				'.gform_wrapper',
				'.gform_wrapper .gfield_label',
				'.gform_wrapper .gfield_description',
				'.gform_wrapper .ginput_container input',
				'.gform_wrapper .ginput_container textarea',
				'.gform_wrapper .ginput_container select',
				'.gform_wrapper .gform_footer input[type="submit"]',
				'.gform_wrapper .gform_button',
				'.gform_wrapper .gform_page_footer input',
			],
			$negative_exclusions,
			true
		);
		if ( '' !== $selectors ) {
			$css .= $selectors . " {\n\tfont-family: {$family} !important;\n}\n";
		}

		$this->cached_css = $css;
		return $css;
	}

	/**
	 * Consume the existing vazir_font_options['exclude_selectors'] authority and
	 * apply the same bounded element-level selector semantics as the Loader.
	 * Pseudo-elements are intentionally not inserted into relational guards.
	 *
	 * @return string[]
	 */
	private function get_negative_scope_selectors(): array {
		$options = VazirFontPlugin::get_options();
		$exclude = $options['exclude_selectors'] ?? [];
		if ( ! is_array( $exclude ) ) {
			return [];
		}

		$valid = [];
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
	 * Apply root/descendant negative scope. For inheritable declarations, also
	 * reject targets containing an excluded subtree so font inheritance cannot
	 * bypass the exclusion boundary.
	 *
	 * A configured selector containing :has() cannot safely be nested inside the
	 * required descendant-protection :has(). In that case fail closed by omitting
	 * the inheritable GF rule rather than emitting invalid CSS or approximating the
	 * selector with a PHP/DOM matcher.
	 *
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function apply_exclusion_boundary( string $selector, array $exclude_selectors, bool $protect_descendants = false ): string {
		if ( [] === $exclude_selectors ) {
			return $selector;
		}

		$blocked_selectors = [];
		foreach ( $exclude_selectors as $exclude_selector ) {
			$blocked_selectors[] = $exclude_selector;
			$blocked_selectors[] = $exclude_selector . ' *';
		}

		$guarded = $selector . ':not(:where(' . implode( ', ', $blocked_selectors ) . '))';
		if ( ! $protect_descendants ) {
			return $guarded;
		}

		foreach ( $exclude_selectors as $exclude_selector ) {
			if ( false !== stripos( $exclude_selector, ':has(' ) ) {
				return '';
			}
		}

		return $guarded . ':not(:has(:where(' . implode( ', ', $exclude_selectors ) . ')))';
	}

	/**
	 * @param string[] $selectors Internal GF enforcement selectors.
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function build_enforcement_selector_list( array $selectors, array $exclude_selectors, bool $protect_descendants = false ): string {
		$guarded = [];
		foreach ( $selectors as $selector ) {
			$candidate = $this->apply_exclusion_boundary( $selector, $exclude_selectors, $protect_descendants );
			if ( '' !== $candidate ) {
				$guarded[] = $candidate;
			}
		}
		return implode( ",\n", $guarded );
	}

	/**
	 * Split only commas at selector-list top level, matching Loader behavior.
	 *
	 * @return string[]
	 */
	private function split_top_level_selector_list( string $selector ): array {
		$parts         = [];
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
		$selector = str_ireplace( [ '@import', 'url(' ], '', $selector );
		$selector = (string) preg_replace( '/\/\*.*?\*\//', '', $selector );
		$selector = str_replace( [ '{', '}', ';' ], ' ', $selector );
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

	private function is_enabled(): bool {
		$options = VazirFontPlugin::get_options();
		return ! empty( $options['enable_gravity_forms'] );
	}

	private function is_gravity_forms_admin_screen(): bool {
		if ( class_exists( 'GFForms' ) && method_exists( 'GFForms', 'is_gravity_page' ) ) {
			return (bool) GFForms::is_gravity_page();
		}
		if ( class_exists( 'RGForms' ) && method_exists( 'RGForms', 'is_gravity_page' ) ) {
			return (bool) RGForms::is_gravity_page();
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}
		return false !== strpos( $screen->id, 'gf_' ) || false !== strpos( $screen->id, 'gravityforms' );
	}
}
