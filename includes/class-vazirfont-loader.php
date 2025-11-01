<?php
/**
 * Font loader for Vazir font plugin.
 *
 * @package Vazir_Font_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles enqueueing and styling of the Vazir font.
 *
 * @package Vazir_Font_WP
 */
class VazirFont_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var VazirFont_Loader|null
	 */
	private static $instance = null;

	/**
	 * Supported font weights.
	 *
	 * @var array<string, string>
	 */
	private $font_weights = array(
		'300' => 'Light',
		'400' => 'Regular',
		'500' => 'Medium',
		'700' => 'Bold',
		'900' => 'Black',
	);

	/**
	 * Cached sanitised font weights selected by the user.
	 *
	 * @var array<string>|null
	 */
	private $selected_weights = null;

	/**
	 * Normalise the supplied font weight selections.
	 *
	 * @param array $weights Raw option values.
	 * @return array<string>
	 */
	private static function get_selected_weights( $weights ) {
		$allowed_weights = array( '300', '400', '500', '700', '900' );

		$weights = array_map( 'strval', (array) $weights );
		$weights = array_map( 'trim', $weights );
		$weights = array_values( array_unique( array_intersect( $weights, $allowed_weights ) ) );

		if ( empty( $weights ) ) {
			$weights = array( '400' );
		}

		return $weights;
	}

	/**
	 * Retrieve the sanitised font weights from stored options.
	 *
	 * @return array<string>
	 */
	private function get_selected_option_weights() {
		if ( null === $this->selected_weights ) {
			$options     = VazirFontPlugin::get_options();
			$raw_weights = isset( $options['font_weights'] ) ? $options['font_weights'] : array();
			$this->selected_weights = self::get_selected_weights( $raw_weights );
		}

		return $this->selected_weights;
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @return VazirFont_Loader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialise hooks.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register the hooks required to load fonts in each context.
	 */
	private function init_hooks() {
		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_fonts' ), 5 );
		add_action( 'wp_head', array( $this, 'add_font_preload' ), 1 );
		add_action( 'wp_head', array( $this, 'add_frontend_styles' ), 99 );

		// Admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_fonts' ), 5 );
		add_action( 'admin_head', array( $this, 'add_font_preload' ), 1 );
		add_action( 'admin_head', array( $this, 'add_admin_styles' ), 99 );

		// Login.
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_fonts' ), 5 );
		add_action( 'login_head', array( $this, 'add_font_preload' ), 1 );
		add_action( 'login_head', array( $this, 'add_login_styles' ), 20 );

		// Block editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_fonts' ), 5 );

		// Cache clearing.
		add_action( 'vazir_font_clear_cache', array( $this, 'clear_cache' ) );
	}

	/**
	 * Wrapper for frontend enqueue.
	 */
	public function enqueue_frontend_fonts() {
		$this->enqueue_font_files( 'frontend' );
	}

	/**
	 * Wrapper for admin enqueue.
	 */
	public function enqueue_admin_fonts() {
		$this->enqueue_font_files( 'admin' );
	}

	/**
	 * Wrapper for login enqueue.
	 */
	public function enqueue_login_fonts() {
		$this->enqueue_font_files( 'login' );
	}

	/**
	 * Wrapper for the block editor enqueue.
	 */
	public function enqueue_block_editor_fonts() {
		$this->enqueue_font_files( 'admin' );
	}

	/**
	 * Output frontend inline styles.
	 */
	public function add_frontend_styles() {
		$this->output_custom_styles( 'frontend' );
		$this->output_custom_styles( 'frontend', $this->get_base_font_css( 'frontend' ) );
	}

	/**
	 * Output admin inline styles.
	 */
	public function add_admin_styles() {
		$this->output_custom_styles( 'admin' );
		$this->output_custom_styles( 'admin', $this->get_base_font_css( 'admin' ) );
	}

	/**
	 * Output login inline styles.
	 */
	public function add_login_styles() {
		$this->output_custom_styles( 'login' );
	}

	/**
	 * Output Gravity Forms inline styles.
	 */
	public function add_gravityforms_styles() {
		$this->output_custom_styles( 'gravityforms' );
	}

	/**
	 * Enqueue font files for the supplied context.
	 *
	 * @param string $context Context identifier.
	 */
	public function enqueue_font_files( $context ) {
		if ( ! $this->should_load_context( $context ) ) {
			return;
		}

		$weights = $this->get_selected_option_weights();

		$version = VAZIR_FONT_VERSION . '.' . implode( '', $weights );

		wp_enqueue_style(
			'vazir-font-' . $context,
			VAZIR_FONT_ASSETS_URL . 'css/vazir-fonts.css',
			array(),
			$version
		);

		$css = self::generate_font_faces( $weights );
		wp_add_inline_style( 'vazir-font-' . $context, $css );
	}

	/**
	 * Output preload tags for the current context.
	 */
	public function add_font_preload() {
		$context = $this->get_context_from_filter( current_filter() );

		if ( ! $this->should_load_context( $context ) ) {
			return;
		}

		$weights = $this->get_selected_option_weights();

		$preload_order = array_values( array_intersect( array( '400', '700', '500', '300', '900' ), $weights ) );

		foreach ( $preload_order as $weight ) {
			$font_name = 'vazir-' . $weight;

			printf(
				'<link rel="preload" href="%1$s" as="font" type="font/woff2" crossorigin="anonymous" />' . "\n",
				esc_url( VAZIR_FONT_FONTS_URL . $font_name . '.woff2' )
			);
		}
	}

	/**
	 * Output context-specific inline CSS.
	 *
	 * @param string      $context Context identifier.
	 * @param string|null $css     Custom CSS to output. Optional.
	 */
	private function output_custom_styles( $context, $css = null ) {
		if ( ! $this->should_load_context( $context ) ) {
			return;
		}

		if ( null === $css ) {
			$options           = VazirFontPlugin::get_options();
			$exclude_selectors = isset( $options['exclude_selectors'] ) ? (array) $options['exclude_selectors'] : array();
			$css               = '';

			switch ( $context ) {
				case 'frontend':
					$css = $this->get_frontend_css( $exclude_selectors );
					break;
				case 'admin':
					$css = $this->get_admin_css( $exclude_selectors );
					break;
				case 'login':
					$css = $this->get_login_css( $exclude_selectors );
					break;
				case 'gravityforms':
					$css = $this->get_gravityforms_css( $exclude_selectors );
					break;
			}
		}

		if ( ! is_string( $css ) || '' === trim( $css ) ) {
			return;
		}

		$handle = 'vazir-font-' . $context;

		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			if ( defined( 'VAZIR_FONT_ASSETS_URL' ) ) {
				wp_register_style(
					$handle,
					VAZIR_FONT_ASSETS_URL . 'css/vazir-fonts.css',
					array(),
					VAZIR_FONT_VERSION
				);
			} else {
				wp_register_style(
					$handle,
					false,
					array(),
					VAZIR_FONT_VERSION
				);
			}

			wp_enqueue_style( $handle );
		}

		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Build @font-face declarations for selected weights.
	 *
	 * @param array $weights Font weights.
	 * @return string
	 */
	public static function generate_font_faces( $weights ) {
		$css = '';

		$weights = array_map( 'strval', (array) $weights );
		$weights = array_values( array_unique( $weights ) );

		if ( empty( $weights ) ) {
			$weights = array( '400' );
		}

		$font_dir = trailingslashit( VAZIR_FONT_PLUGIN_DIR ) . 'assets/fonts/';

		foreach ( $weights as $weight ) {
			if ( '' === $weight ) {
				continue;
			}

			$font_name = 'vazir-' . $weight;
			$sources   = array(
				'woff2' => array(
					'path'   => $font_dir . $font_name . '.woff2',
					'format' => 'woff2',
				),
				'woff'  => array(
					'path'   => $font_dir . $font_name . '.woff',
					'format' => 'woff',
				),
				'ttf'   => array(
					'path'   => $font_dir . $font_name . '.ttf',
					'format' => 'truetype',
				),
			);

			$src = array();

			foreach ( $sources as $extension => $source ) {
				if ( file_exists( $source['path'] ) ) {
					$src[] = "url('" . esc_url( VAZIR_FONT_FONTS_URL . $font_name . '.' . $extension ) . "') format(\"{$source['format']}\")";
				}
			}

			if ( empty( $src ) ) {
				continue;
			}

			$css .= "@font-face {\n";
			$css .= "\tfont-family: 'Vazir';\n";
			$css .= "\tfont-style: normal;\n";
			$css .= "\tfont-weight: {$weight};\n";
			$css .= "\tfont-display: swap;\n";
			$css .= "\tsrc: " . implode( ",\n\t\t", $src ) . ";\n";
			$css .= "}\n\n";
		}
		return $css;
	}

	/**
	 * Clear any generated caches.
	 */
	public function clear_cache() {
		$this->regenerate_font_files();
	}

	/**
	 * Determine if the given context should load fonts.
	 *
	 * @param string $context Context identifier.
	 * @return bool
	 */
	private function should_load_context( $context ) {
		$options = VazirFontPlugin::get_options();

		if ( 'frontend' === $context ) {
			return ! empty( $options['enable_frontend'] );
		}

		if ( in_array( $context, array( 'admin', 'login' ), true ) ) {
			return ! empty( $options['enable_admin'] );
		}

		if ( 'gravityforms' === $context ) {
			return ! empty( $options['enable_gravity_forms'] );
		}

		return true;
	}

	/**
	 * Map the current filter to a loader context.
	 *
	 * @param string $filter Current filter name.
	 * @return string
	 */
	private function get_context_from_filter( $filter ) {
		switch ( $filter ) {
			case 'admin_head':
			case 'admin_enqueue_scripts':
				return 'admin';
			case 'login_head':
			case 'login_enqueue_scripts':
				return 'login';
			default:
				return 'frontend';
		}
	}

	/**
	 * Build CSS for the frontend context.
	 *
	 * @param array $exclude_selectors Selectors to exclude.
	 * @return string
	 */
	private function get_frontend_css( $exclude_selectors ) {
		return $this->build_font_css(
			$exclude_selectors,
			array(
				'body',
				'button',
				'input',
				'select',
				'textarea',
				'.editor-styles-wrapper',
			)
		);
	}

	/**
	 * Build CSS for the admin context.
	 *
	 * @param array $exclude_selectors Selectors to exclude.
	 * @return string
	 */
	private function get_admin_css( $exclude_selectors ) {
		return $this->build_font_css(
			$exclude_selectors,
			array(
				'body.wp-admin',
				'#wpadminbar',
				'.wrap',
				'.wp-core-ui .button',
				'.wp-core-ui input',
			)
		);
	}

	/**
	 * Build CSS for the login context.
	 *
	 * @param array $exclude_selectors Selectors to exclude.
	 * @return string
	 */
	private function get_login_css( $exclude_selectors ) {
		return $this->build_font_css(
			$exclude_selectors,
			array(
				'body.login',
				'#loginform',
				'#loginform input',
				'.message',
			)
		);
	}

	/**
	 * Build CSS for Gravity Forms.
	 *
	 * @param array $exclude_selectors Selectors to exclude.
	 * @return string
	 */
	private function get_gravityforms_css( $exclude_selectors ) {
		return $this->build_font_css(
			$exclude_selectors,
			array(
				'.gform_wrapper',
				'.gform_wrapper input',
				'.gform_wrapper select',
				'.gform_wrapper textarea',
				'.gform_wrapper .gfield_label',
			)
		);
	}

	/**
	 * Build base font CSS for the supplied context.
	 *
	 * @param string $context Context identifier.
	 * @return string
	 */
	private function get_base_font_css( $context ) {
		$family = apply_filters(
			'vazir_font_family',
			"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);

		if ( 'admin' === $context ) {
			return "html body.wp-admin, #wpwrap, .wrap, input, textarea, select, button { font-family: {$family} !important; }\n";
		}

		return "html body, input, textarea, select, button { font-family: {$family}; }\n";
	}

	/**
	 * Build CSS rules applying the font stack.
	 *
	 * @param array $exclude_selectors Selectors to exclude.
	 * @param array $base_selectors    Base selectors to target.
	 * @return string
	 */
	private function build_font_css( $exclude_selectors, $base_selectors ) {
		$font_stack = "font-family: 'Vazir', 'Tahoma', 'Iranian Sans', system-ui, -apple-system, sans-serif;";
		$rules      = '';

		foreach ( $base_selectors as $selector ) {
			$rules .= sprintf( '%1$s { %2$s }\n', $selector, $font_stack );
		}

		if ( ! empty( $exclude_selectors ) ) {
			foreach ( $exclude_selectors as $selector ) {
				$sanitized = sanitize_text_field( $selector );

				if ( '' === $sanitized ) {
					continue;
				}

				$rules .= sprintf( '%1$s { font-family: inherit; }\n', $sanitized );
			}
		}

		if ( is_rtl() ) {
			$rules .= "[dir='rtl'] body { letter-spacing: normal; }\n";
		}

		return $rules;
	}

	/**
	 * Placeholder for regenerating cached font files.
	 */
	private function regenerate_font_files() {
		// Intentionally left blank. Extend if caching is introduced.
	}
}
