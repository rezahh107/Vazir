<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads bundled Vazir font assets across WordPress contexts.
 *
 * The broad admin rules are intentionally retained as compatibility behavior
 * until browser characterization can prove a lower-specificity replacement is
 * rendering-equivalent across wp-admin and third-party controls.
 */
final class VazirFont_Loader {
	private static ?self $instance = null;
	private ?array $selected_weights = null;
	private bool $gravityforms_requested = false;
	private array $inline_handles = [];

	private array $supported_weights = [
		'300' => 'Light',
		'400' => 'Regular',
		'500' => 'Medium',
		'700' => 'Bold',
		'900' => 'Black',
	];

	private function __construct() {
		$this->init_hooks();
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_Loader singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function init_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_fonts' ], 5 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_fonts' ], 5 );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_login_fonts' ], 5 );

		// WordPress 6.3+ loads enqueue_block_assets into the editor content iframe.
		// WordPress 7.1 always iframes the post editor, matching Site Editor behavior.
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_content_fonts' ], 5 );

		add_filter( 'body_class', [ $this, 'filter_body_class' ] );
		add_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ] );
		add_filter( 'login_body_class', [ $this, 'filter_login_body_class' ] );
	}

	public function mark_gravityforms_request(): void {
		$this->gravityforms_requested = true;
	}

	public function enqueue_frontend_fonts(): void {
		if ( ! $this->should_load_context( 'frontend' ) ) {
			return;
		}
		$this->enqueue_inline_style( 'vazir-font-frontend', $this->get_inline_css( 'frontend' ) );
	}

	public function enqueue_admin_fonts(): void {
		if ( ! $this->should_load_context( 'admin' ) ) {
			return;
		}
		$this->enqueue_inline_style( 'vazir-font-admin-runtime', $this->get_inline_css( 'admin' ) );
	}

	public function enqueue_login_fonts(): void {
		if ( ! $this->should_load_context( 'login' ) ) {
			return;
		}
		$this->enqueue_inline_style( 'vazir-font-login', $this->get_inline_css( 'login' ) );
	}

	/**
	 * Enqueue typography inside the block/Site Editor content canvas.
	 *
	 * enqueue_block_assets runs on both frontend and editor; this plugin already
	 * has a dedicated frontend loader, so the admin check keeps this copy limited
	 * to editor content and avoids duplicate frontend output.
	 */
	public function enqueue_editor_content_fonts(): void {
		if ( ! is_admin() || ! $this->should_load_context( 'block_editor' ) ) {
			return;
		}
		$this->enqueue_inline_style( 'vazir-font-editor-content', $this->get_inline_css( 'editor' ) );
	}

	/**
	 * Public read-only font-face surface for compatibility adapters.
	 */
	public function get_font_face_css(): string {
		return $this->generate_font_faces( $this->get_selected_weights() );
	}

	/**
	 * Public read-only selected weight surface for compatibility adapters/tests.
	 *
	 * @return string[]
	 */
	public function get_selected_weights(): array {
		if ( null !== $this->selected_weights ) {
			return $this->selected_weights;
		}

		$options = VazirFontPlugin::get_options();
		$weights = $options['font_weights'] ?? [];
		if ( ! is_array( $weights ) ) {
			$weights = [];
		}

		$weights = array_map( 'strval', $weights );
		$weights = array_values( array_unique( array_intersect( $weights, array_keys( $this->supported_weights ) ) ) );
		if ( [] === $weights ) {
			$weights = [ '400' ];
		}
		if ( ! in_array( '400', $weights, true ) ) {
			array_unshift( $weights, '400' );
			$weights = array_values( array_unique( $weights ) );
		}

		$this->selected_weights = $weights;
		return $weights;
	}

	private function enqueue_inline_style( string $handle, string $css ): void {
		if ( '' === trim( $css ) ) {
			return;
		}

		if ( isset( $this->inline_handles[ $handle ] ) ) {
			return;
		}

		$version = VAZIR_FONT_VERSION . '.' . implode( '', $this->get_selected_weights() );
		wp_register_style( $handle, false, [], $version );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
		$this->inline_handles[ $handle ] = true;
	}

	private function should_load_context( string $context ): bool {
		$options = VazirFontPlugin::get_options();
		switch ( $context ) {
			case 'frontend':
				return ! empty( $options['enable_frontend'] );
			case 'admin':
			case 'login':
			case 'block_editor':
				return ! empty( $options['enable_admin'] );
			case 'gravityforms':
				return ! empty( $options['enable_gravity_forms'] );
			default:
				return false;
		}
	}

	private function get_inline_css( string $context ): string {
		$exclude_selectors = $this->get_exclude_selectors();
		$parts             = [
			$this->get_font_face_css(),
			$this->get_context_css( $context, $exclude_selectors ),
			$this->get_base_font_css( $context, $exclude_selectors ),
		];
		$parts = array_filter( array_map( 'trim', $parts ) );
		return implode( "\n\n", $parts );
	}

	/**
	 * Generate only sources that physically exist in the package.
	 *
	 * @param string[] $weights Selected weights.
	 */
	private function generate_font_faces( array $weights ): string {
		if ( [] === $weights ) {
			$weights = [ '400' ];
		}

		$css = '';
		foreach ( $weights as $weight ) {
			if ( ! isset( $this->supported_weights[ $weight ] ) ) {
				continue;
			}
			$url = VAZIR_FONT_FONTS_URL . 'vazirmatn-' . $weight . '.woff2';
			$css .= "@font-face {\n";
			$css .= "\tfont-family: 'Vazirmatn';\n";
			$css .= "\tfont-style: normal;\n";
			$css .= "\tfont-weight: {$weight};\n";
			$css .= "\tfont-display: swap;\n";
			$css .= "\tsrc: url('" . esc_url( $url ) . "') format('woff2');\n";
			$css .= "}\n\n";
		}
		return $css;
	}

	/**
	 * @return string[]
	 */
	private function get_exclude_selectors(): array {
		$options = VazirFontPlugin::get_options();
		$exclude = $options['exclude_selectors'] ?? [];
		return is_array( $exclude ) ? $exclude : [];
	}

	/**
	 * @param string[] $exclude_selectors Exclusions from settings.
	 */
	private function get_context_css( string $context, array $exclude_selectors ): string {
		switch ( $context ) {
			case 'frontend':
				return $this->build_font_css( $exclude_selectors, [ 'body', 'button', 'input', 'select', 'textarea' ], true );
			case 'admin':
				return $this->build_font_css( $exclude_selectors, [ 'body.wp-admin', '#wpadminbar', '.wrap', '.wp-core-ui .button', '.wp-core-ui input' ], true );
			case 'login':
				return $this->build_font_css( $exclude_selectors, [ 'body.login', '#loginform', '#loginform input', '.message' ], true );
			case 'editor':
				return $this->build_font_css( $exclude_selectors, [ '.editor-styles-wrapper', '.editor-styles-wrapper button', '.editor-styles-wrapper input', '.editor-styles-wrapper select', '.editor-styles-wrapper textarea' ], false );
			default:
				return '';
		}
	}

	/**
	 * @param string[] $exclude_selectors Exclusions from settings.
	 */
	private function get_base_font_css( string $context, array $exclude_selectors ): string {
		$family              = $this->get_font_family();
		$textual_elements    = 'h1, h2, h3, h4, h5, h6, p, li, dt, dd, blockquote, figcaption, table, th, td, label, legend';
		$negative_exclusions = $this->get_negative_scope_selectors( $exclude_selectors );

		if ( 'admin' === $context ) {
			$selectors = $this->build_enforcement_selector_list(
				[
					'.vazir-font-enabled',
					'.vazir-font-enabled #wpwrap',
					'.vazir-font-enabled .wrap',
					'.vazir-font-enabled input',
					'.vazir-font-enabled textarea',
					'.vazir-font-enabled select',
					'.vazir-font-enabled button',
				],
				$negative_exclusions
			);

			return $selectors . " {\n"
				. "\tfont-family: {$family} !important;\n"
				. "}\n\n"
				. ".vazir-font-enabled .dashicons,\n"
				. ".vazir-font-enabled .dashicons:before,\n"
				. ".vazir-font-enabled .dashicons-before:before {\n"
				. "\tfont-family: 'dashicons' !important;\n"
				. "}\n";
		}

		if ( 'editor' === $context ) {
			$root_selector = $this->apply_exclusion_boundary( '.editor-styles-wrapper', $negative_exclusions );
			$text_selector = $this->apply_exclusion_boundary(
				".editor-styles-wrapper :where({$textual_elements}, input, textarea, select, button)",
				$negative_exclusions
			);

			return $root_selector . " {\n\tfont-family: {$family};\n}\n"
				. $text_selector . " {\n\tfont-family: inherit;\n}\n";
		}

		$selectors = $this->build_enforcement_selector_list(
			[
				'.vazir-font-enabled',
				'.vazir-font-enabled input',
				'.vazir-font-enabled textarea',
				'.vazir-font-enabled select',
				'.vazir-font-enabled button',
			],
			$negative_exclusions
		);
		$text_selector = $this->apply_exclusion_boundary(
			".vazir-font-enabled :where({$textual_elements})",
			$negative_exclusions
		);

		return $selectors . " {\n"
			. "\tfont-family: {$family};\n"
			. "}\n"
			// Twenty Twenty-One and similar classic themes set font-family directly
			// on headings/text. Browser characterization proves inheritance alone
			// can resolve through a theme-styled ancestor, so this !important rule
			// directly enforces Vazir only on non-excluded textual elements.
			. $text_selector . " {\n\tfont-family: {$family} !important;\n}\n";
	}

	private function get_font_family(): string {
		return apply_filters(
			'vazir_font_family',
			"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);
	}

	/**
	 * @param string[] $exclude_selectors Exclusions from settings.
	 * @param string[] $base_selectors Base selectors for the context.
	 */
	private function build_font_css( array $exclude_selectors, array $base_selectors, bool $scope ): string {
		$family              = $this->get_font_family();
		$rules               = '';
		$negative_exclusions = $this->get_negative_scope_selectors( $exclude_selectors );

		foreach ( $base_selectors as $selector ) {
			$candidate = $scope ? $this->scope_selector( $selector ) : $selector;
			$candidate = $this->sanitize_css_selector( $candidate );
			if ( '' === $candidate || ! $this->is_valid_css_selector( $candidate ) ) {
				continue;
			}

			$candidate = $this->apply_exclusion_boundary( $candidate, $negative_exclusions );
			$rules    .= $candidate . " {\n\tfont-family: {$family};\n}\n";
		}

		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			$rules .= $scope
				? "[dir='rtl'] .vazir-font-enabled {\n\tletter-spacing: normal;\n}\n"
				: "[dir='rtl'] .editor-styles-wrapper {\n\tletter-spacing: normal;\n}\n";
		}

		return $rules;
	}

	/**
	 * Turn user exclusions into safe element-level predicates for Vazir rules.
	 *
	 * Each top-level selector-list member is evaluated independently so a safe
	 * element selector remains effective when the same setting line also contains
	 * a pseudo-element selector. Pseudo-elements are not inserted into relational
	 * guards because generic Vazir rules do not directly target pseudo-elements.
	 *
	 * @param string[] $exclude_selectors Exclusions from settings.
	 * @return string[]
	 */
	private function get_negative_scope_selectors( array $exclude_selectors ): array {
		$valid = [];

		foreach ( $exclude_selectors as $selector ) {
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
	 * Split only commas at selector-list top level. Commas inside functional
	 * pseudo-classes or attribute values remain part of their original selector.
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

	/**
	 * Prevent a Vazir enforcement selector from matching an excluded root or any
	 * element below an excluded root. :where() accepts a complex selector list and
	 * contributes zero specificity, so settings do not strengthen plugin rules.
	 *
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function apply_exclusion_boundary( string $selector, array $exclude_selectors ): string {
		if ( [] === $exclude_selectors ) {
			return $selector;
		}

		$blocked_selectors = [];
		foreach ( $exclude_selectors as $exclude_selector ) {
			$blocked_selectors[] = $exclude_selector;
			$blocked_selectors[] = $exclude_selector . ' *';
		}

		return $selector . ':not(:where(' . implode( ', ', $blocked_selectors ) . '))';
	}

	/**
	 * @param string[] $selectors Valid internal enforcement selectors.
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 */
	private function build_enforcement_selector_list( array $selectors, array $exclude_selectors ): string {
		$guarded = [];
		foreach ( $selectors as $selector ) {
			$guarded[] = $this->apply_exclusion_boundary( $selector, $exclude_selectors );
		}
		return implode( ",\n", $guarded );
	}

	private function selector_targets_pseudo_element( string $selector ): bool {
		return 1 === preg_match( '/::[a-zA-Z0-9_-]+|:(?:before|after|first-letter|first-line)\b/i', $selector );
	}

	private function scope_selector( string $selector ): string {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return '';
		}
		if ( 0 === strpos( $selector, '.vazir-font-enabled' ) ) {
			return $selector;
		}
		if ( 0 === strpos( $selector, 'body' ) ) {
			return '.vazir-font-enabled' . substr( $selector, 4 );
		}
		return '.vazir-font-enabled ' . $selector;
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

	public function filter_body_class( array $classes ): array {
		if ( $this->should_load_context( 'frontend' ) || ( $this->gravityforms_requested && $this->should_load_context( 'gravityforms' ) ) ) {
			$classes[] = 'vazir-font-enabled';
		}
		return array_values( array_unique( $classes ) );
	}

	public function filter_admin_body_class( string $classes ): string {
		if ( $this->should_load_context( 'admin' ) || ( $this->gravityforms_requested && $this->should_load_context( 'gravityforms' ) ) ) {
			$classes = trim( $classes );
			$classes .= ( '' === $classes ? '' : ' ' ) . 'vazir-font-enabled';
		}
		return $classes;
	}

	public function filter_login_body_class( array $classes ): array {
		if ( $this->should_load_context( 'login' ) ) {
			$classes[] = 'vazir-font-enabled';
		}
		return array_values( array_unique( $classes ) );
	}
}
