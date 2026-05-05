<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads Vazir font assets and inline CSS across WordPress contexts.
 *
 * @package Vazir_Font_WP
 */
final class VazirFont_Loader {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Cached selected font weights for current request.
	 *
	 * @var string[]|null
	 */
	private ?array $selected_weights = null;

	/**
	 * Whether Gravity Forms assets were requested in this request.
	 */
	private bool $gravityforms_requested = false;

	/**
	 * Supported font weights.
	 *
	 * @var array<string, string>
	 */
	private array $supported_weights = [
		'300' => 'Light',
		'400' => 'Regular',
		'500' => 'Medium',
		'700' => 'Bold',
		'900' => 'Black',
	];

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_Loader singleton.' );
	}

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all hooks.
	 */
	private function init_hooks(): void {
		// Frontend.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_fonts' ], 5 );
		add_action( 'wp_head', [ $this, 'print_frontend_inline_css' ], 20 );

		// Admin.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_fonts' ], 5 );
		add_action( 'admin_head', [ $this, 'print_admin_inline_css' ], 20 );

		// Login.
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_login_fonts' ], 5 );
		add_action( 'login_head', [ $this, 'print_login_inline_css' ], 20 );

		// Block editor.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_fonts' ], 5 );

		// Cache clearing hook.
		add_action( VAZIR_FONT_CRON_HOOK, [ $this, 'clear_cache' ] );

		// Body classes.
		add_filter( 'body_class', [ $this, 'filter_body_class' ] );
		add_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ] );
		add_filter( 'login_body_class', [ $this, 'filter_login_body_class' ] );
	}

	/**
	 * Mark that Gravity Forms is being rendered (used for body classes).
	 */
	public function mark_gravityforms_request(): void {
		$this->gravityforms_requested = true;
	}

	/**
	 * Enqueue frontend assets (preload + font files).
	 */
	public function enqueue_frontend_fonts(): void {
		if ( ! $this->should_load_context( 'frontend' ) ) {
			return;
		}
		$this->enqueue_font_files( 'frontend' );
		$this->add_font_preload();
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_fonts(): void {
		if ( ! $this->should_load_context( 'admin' ) ) {
			return;
		}
		$this->enqueue_font_files( 'admin' );
	}

	/**
	 * Enqueue login assets.
	 */
	public function enqueue_login_fonts(): void {
		if ( ! $this->should_load_context( 'login' ) ) {
			return;
		}
		$this->enqueue_font_files( 'login' );
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_fonts(): void {
		if ( ! $this->should_load_context( 'block_editor' ) ) {
			return;
		}
		$this->enqueue_font_files( 'block_editor' );

		$handle = 'vazir-font-block-editor-inline';
		wp_register_style( $handle, false, [], VAZIR_FONT_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $this->get_inline_css( 'frontend' ) );
	}

	/**
	 * Print frontend inline CSS.
	 */
	public function print_frontend_inline_css(): void {
		if ( ! $this->should_load_context( 'frontend' ) ) {
			return;
		}
		$this->print_inline_style_tag( 'frontend' );
	}

	/**
	 * Print admin inline CSS.
	 */
	public function print_admin_inline_css(): void {
		if ( ! $this->should_load_context( 'admin' ) ) {
			return;
		}
		$this->print_inline_style_tag( 'admin' );
	}

	/**
	 * Print login inline CSS.
	 */
	public function print_login_inline_css(): void {
		if ( ! $this->should_load_context( 'login' ) ) {
			return;
		}
		$this->print_inline_style_tag( 'login' );
	}

	/**
	 * Render inline style tag for a given context.
	 */
	private function print_inline_style_tag( string $context ): void {
		$css = $this->get_inline_css( $context );
		if ( '' === trim( $css ) ) {
			return;
		}
		// CSS already sanitized and validated via build_font_css().
		echo '<style id="vazir-font-' . esc_attr( $context ) . '-inline-css">' . "\n" . $css . "\n</style>\n";
	}

	/**
	 * Enqueue font faces for contexts that need a dedicated style handle.
	 */
	private function enqueue_font_files( string $context ): void {
		$weights = $this->get_selected_weights();
		if ( [] === $weights ) {
			return;
		}

		// Frontend & admin use only inline CSS (no separate handle).
		if ( in_array( $context, [ 'frontend', 'admin' ], true ) ) {
			return;
		}

		$version = VAZIR_FONT_VERSION . '.' . implode( '', $weights );
		$handle  = 'vazir-font-' . $context;

		wp_register_style( $handle, false, [], $version );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $this->generate_font_faces( $weights ) );
	}

	/**
	 * Add preload links for selected font weights (frontend only).
	 */
	private function add_font_preload(): void {
		$weights = $this->get_selected_weights();
		if ( [] === $weights ) {
			return;
		}

		$order = [ '400', '700', '500', '300', '900' ];
		foreach ( $order as $weight ) {
			if ( ! in_array( $weight, $weights, true ) ) {
				continue;
			}
			$url = VAZIR_FONT_FONTS_URL . 'vazir-' . $weight . '.woff2';
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
		}
	}

	/**
	 * Get selected and validated font weights.
	 *
	 * @return string[]
	 */
	private function get_selected_weights(): array {
		if ( null !== $this->selected_weights ) {
			return $this->selected_weights;
		}

		$options = VazirFontPlugin::get_options();
		$weights = $options['font_weights'] ?? [];

		if ( ! is_array( $weights ) ) {
			$weights = [];
		}

		$weights = array_map( 'strval', $weights );
		$weights = array_values(
			array_intersect(
				$weights,
				array_keys( $this->supported_weights )
			)
		);

		$weights = array_values( array_unique( $weights ) );

		if ( [] === $weights ) {
			$weights = [ '400' ];
		}

		// Ensure '400' (Regular) is always present.
		if ( ! in_array( '400', $weights, true ) ) {
			array_unshift( $weights, '400' );
			$weights = array_values( array_unique( $weights ) );
		}

		$this->selected_weights = $weights;
		return $weights;
	}

	/**
	 * Determine whether a context should load.
	 */
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
				return true;
		}
	}

	/**
	 * Get complete inline CSS for a context.
	 */
	private function get_inline_css( string $context ): string {
		$weights = $this->get_selected_weights();

		$parts = [
			$this->generate_font_faces( $weights ),
			$this->get_context_css( $context ),
			$this->get_base_font_css( $context ),
		];

		$parts = array_filter( array_map( 'trim', $parts ) );
		if ( empty( $parts ) ) {
			return '';
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Generate @font-face declarations for selected weights.
	 *
	 * @param string[] $weights
	 */
	private function generate_font_faces( array $weights ): string {
		if ( empty( $weights ) ) {
			$weights = [ '400' ];
		}

		$css = '';
		foreach ( $weights as $weight ) {
			$font_name = 'vazir-' . $weight;
			$src_parts = [];

			$extensions = [
				'woff2' => 'woff2',
				'woff'  => 'woff',
				'ttf'   => 'truetype',
			];

			foreach ( $extensions as $ext => $format ) {
				$url = VAZIR_FONT_FONTS_URL . $font_name . '.' . $ext;
				$src_parts[] = "url('" . esc_url( $url ) . "') format('{$format}')";
			}

			if ( empty( $src_parts ) ) {
				continue;
			}

			$css .= "@font-face {\n";
			$css .= "\tfont-family: 'Vazir';\n";
			$css .= "\tfont-style: normal;\n";
			$css .= "\tfont-weight: {$weight};\n";
			$css .= "\tfont-display: swap;\n";
			$css .= "\tsrc: " . implode( ",\n\t\t", $src_parts ) . ";\n";
			$css .= "}\n\n";
		}

		return $css;
	}

	/**
	 * Get context‑specific CSS (selectors for font application).
	 */
	private function get_context_css( string $context ): string {
		$options = VazirFontPlugin::get_options();
		$exclude = $options['exclude_selectors'] ?? [];
		if ( ! is_array( $exclude ) ) {
			$exclude = [];
		}

		switch ( $context ) {
			case 'frontend':
				$base = [
					'body',
					'button',
					'input',
					'select',
					'textarea',
					'.editor-styles-wrapper',
				];
				return $this->build_font_css( $exclude, $base );
			case 'admin':
				$base = [
					'body.wp-admin',
					'#wpadminbar',
					'.wrap',
					'.wp-core-ui .button',
					'.wp-core-ui input',
				];
				return $this->build_font_css( $exclude, $base );
			case 'login':
				$base = [
					'body.login',
					'#loginform',
					'#loginform input',
					'.message',
				];
				return $this->build_font_css( $exclude, $base );
			case 'gravityforms':
				$base = [
					'.gform_wrapper',
					'.gform_wrapper .gfield_label',
					'.gform_wrapper .ginput_container input',
					'.gform_wrapper .ginput_container textarea',
					'.gform_wrapper .ginput_container select',
					'.gform_wrapper .gform_footer input[type="submit"]',
					'.gform_wrapper .gform_button',
					'.gform_wrapper .gform_page_footer input',
					'.gform_wrapper.gravity-theme .gfield_label',
					'.gform_wrapper.gravity-theme .ginput_complex input',
				];
				return $this->build_font_css( $exclude, $base );
			default:
				return '';
		}
	}

	/**
	 * Get base font CSS that applies to generic selectors and resets icons.
	 */
	private function get_base_font_css( string $context ): string {
		$family = $this->get_font_family();

		if ( 'admin' === $context ) {
			return ".vazir-font-enabled,\n"
				. ".vazir-font-enabled #wpwrap,\n"
				. ".vazir-font-enabled .wrap,\n"
				. ".vazir-font-enabled input,\n"
				. ".vazir-font-enabled textarea,\n"
				. ".vazir-font-enabled select,\n"
				. ".vazir-font-enabled button {\n"
				. "\tfont-family: {$family} !important;\n"
				. "}\n\n"
				. ".vazir-font-enabled .dashicons,\n"
				. ".vazir-font-enabled .dashicons:before,\n"
				. ".vazir-font-enabled .dashicons-before:before {\n"
				. "\tfont-family: 'dashicons' !important;\n"
				. "}\n";
		}

		return ".vazir-font-enabled,\n"
			. ".vazir-font-enabled input,\n"
			. ".vazir-font-enabled textarea,\n"
			. ".vazir-font-enabled select,\n"
			. ".vazir-font-enabled button {\n"
			. "\tfont-family: {$family};\n"
			. "}\n";
	}

	/**
	 * Get font-family stack with filter.
	 */
	private function get_font_family(): string {
		return apply_filters(
			'vazir_font_family',
			"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);
	}

	/**
	 * Build CSS rules for given base selectors + exclude selectors.
	 *
	 * @param string[] $exclude_selectors
	 * @param string[] $base_selectors
	 */
	private function build_font_css( array $exclude_selectors, array $base_selectors ): string {
		$family = $this->get_font_family();
		$rules  = '';

		foreach ( $base_selectors as $selector ) {
			$scoped = $this->scope_selector( $selector );
			$scoped = $this->sanitize_css_selector( $scoped );
			if ( '' === $scoped || ! $this->is_valid_css_selector( $scoped ) ) {
				continue;
			}
			// Selector already validated – safe to output without escaping.
			$rules .= $scoped . " {\n\tfont-family: {$family};\n}\n";
		}

		foreach ( $exclude_selectors as $selector ) {
			$sanitized = $this->sanitize_css_selector( $selector );
			if ( '' === $sanitized ) {
				continue;
			}
			$scoped = $this->scope_selector( $sanitized );
			$scoped = $this->sanitize_css_selector( $scoped );
			if ( '' === $scoped || ! $this->is_valid_css_selector( $scoped ) ) {
				continue;
			}
			$rules .= $scoped . " {\n\tfont-family: inherit;\n}\n";
		}

		if ( is_rtl() ) {
			$rules .= "[dir='rtl'] .vazir-font-enabled {\n\tletter-spacing: normal;\n}\n";
		}

		return $rules;
	}

	/**
	 * Scope a selector under .vazir-font-enabled.
	 */
	private function scope_selector( string $selector ): string {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return '';
		}
		if ( strpos( $selector, '.vazir-font-enabled' ) === 0 ) {
			return $selector;
		}
		if ( strpos( $selector, 'body' ) === 0 ) {
			return '.vazir-font-enabled' . substr( $selector, 4 );
		}
		return '.vazir-font-enabled ' . $selector;
	}

	/**
	 * Sanitize a CSS selector (remove dangerous patterns).
	 */
	private function sanitize_css_selector( string $selector ): string {
		$selector = str_ireplace( [ '@import', 'url(' ], '', $selector );
		$selector = preg_replace( '/\/\*.*?\*\//', '', $selector );
		$selector = str_replace( [ '{', '}', ';' ], ' ', $selector );
		$selector = preg_replace( '/[^a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~]/', '', $selector );
		$selector = trim( preg_replace( '/\s+/', ' ', $selector ) );

		if ( strlen( $selector ) > 200 ) {
			$selector = substr( $selector, 0, 200 );
		}
		return $selector;
	}

	/**
	 * Validate that a sanitized selector is safe to output.
	 */
	private function is_valid_css_selector( string $selector ): bool {
		if ( '' === $selector ) {
			return false;
		}
		if ( strpos( $selector, '{' ) !== false || strpos( $selector, '}' ) !== false || strpos( $selector, ';' ) !== false ) {
			return false;
		}
		if ( strpos( $selector, '/*' ) !== false ) {
			return false;
		}
		if ( ! preg_match( '/^[a-zA-Z.#]/', $selector ) ) {
			return false;
		}
		if ( ! preg_match( '/^[a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~]+$/', $selector ) ) {
			return false;
		}
		$invalid = [ '##', '..', ',,', '>>', '++', '~~', '**' ];
		foreach ( $invalid as $seq ) {
			if ( strpos( $selector, $seq ) !== false ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Add body class for frontend.
	 *
	 * @param string[] $classes
	 * @return string[]
	 */
	public function filter_body_class( array $classes ): array {
		$enable_frontend = $this->should_load_context( 'frontend' );
		$enable_gf       = $this->gravityforms_requested && $this->should_load_context( 'gravityforms' );

		if ( $enable_frontend || $enable_gf ) {
			$classes[] = 'vazir-font-enabled';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Add body class for admin.
	 */
	public function filter_admin_body_class( string $classes ): string {
		$enable_admin = $this->should_load_context( 'admin' );
		$enable_gf    = $this->gravityforms_requested && $this->should_load_context( 'gravityforms' );

		if ( $enable_admin || $enable_gf ) {
			$classes = trim( $classes );
			if ( '' !== $classes ) {
				$classes .= ' ';
			}
			$classes .= 'vazir-font-enabled';
		}
		return $classes;
	}

	/**
	 * Add body class for login.
	 *
	 * @param string[] $classes
	 * @return string[]
	 */
	public function filter_login_body_class( array $classes ): array {
		if ( $this->should_load_context( 'login' ) ) {
			$classes[] = 'vazir-font-enabled';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Clear cache – triggers regeneration and flushes Gravity Forms caches.
	 */
	public function clear_cache(): void {
		$this->regenerate_font_files();
	}

	/**
	 * Regenerate cached font files (especially for Gravity Forms).
	 * Same logic as original loader.
	 */
	private function regenerate_font_files(): void {
		if ( class_exists( 'GFCache' ) ) {
			GFCache::flush();
		}
		if ( function_exists( 'wp_get_upload_dir' ) ) {
			$uploads = wp_get_upload_dir();
			if ( ! empty( $uploads['basedir'] ) ) {
				$pattern = trailingslashit( $uploads['basedir'] ) . 'gravity_forms/*/css/*.css';
				$files   = glob( $pattern );
				if ( is_array( $files ) ) {
					foreach ( $files as $file ) {
						if ( is_string( $file ) && is_file( $file ) && is_readable( $file ) ) {
							wp_delete_file( $file );
						}
					}
				}
			}
		}
		delete_transient( 'gforms_css_version' );
	}
}
