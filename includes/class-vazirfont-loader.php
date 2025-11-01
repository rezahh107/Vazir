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
	 * Flag indicating if Gravity Forms assets were requested.
	 *
	 * @var bool
	 */
	private $gravityforms_requested = false;

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

		if ( ! in_array( '400', $weights, true ) ) {
			array_unshift( $weights, '400' );
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
	 * Retrieve the active font weights for CSS generation.
	 *
	 * @return array<string>
	 */
	private function get_active_weights() {
		return $this->get_selected_option_weights();
	}

	/**
	 * Mark Gravity Forms request for current page load.
	 */
	public function mark_gravityforms_request() {
		$this->gravityforms_requested = true;
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
		add_action( 'wp_head', array( $this, 'add_frontend_styles' ), 20 );

		// Admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_fonts' ), 5 );
		add_action( 'admin_head', array( $this, 'add_font_preload' ), 1 );
		add_action( 'admin_head', array( $this, 'add_admin_styles' ), 20 );

		// Login.
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_fonts' ), 5 );
		add_action( 'login_head', array( $this, 'add_font_preload' ), 1 );
		add_action( 'login_head', array( $this, 'add_login_styles' ), 20 );

		// Block editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_fonts' ), 5 );

		// Cache clearing.
		add_action( 'vazir_font_clear_cache', array( $this, 'clear_cache' ) );

		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );
		add_filter( 'login_body_class', array( $this, 'filter_login_body_class' ) );
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
		if ( ! $this->should_load_context( 'frontend' ) ) {
			return;
		}

		$this->apply_styles_for_context( 'frontend' );
	}

	/**
	 * Output admin inline styles.
	 */
	public function add_admin_styles() {
		if ( ! $this->should_load_context( 'admin' ) ) {
			return;
		}

		$this->apply_styles_for_context( 'admin' );
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
		$this->mark_gravityforms_request();
		$this->apply_styles_for_context( 'gravityforms' );
	}

	/**
	 * Output Gravity Forms styles for admin contexts.
	 */
	public function add_gf_admin_styles() {
		$this->mark_gravityforms_request();
		$this->apply_styles_for_context( 'gravityforms' );
	}

	/**
	 * Apply font styles for the supplied context.
	 *
	 * @param string $context Context identifier.
	 */
	private function apply_styles_for_context( $context ) {
		if ( ! $this->should_load_context( $context ) ) {
			return;
		}

		$weights = $this->get_active_weights();
		$css     = $this->get_inline_css( $context );

		if ( '' === $css ) {
			return;
		}

		$version = VAZIR_FONT_VERSION . '.' . implode( '', $weights );
		$handle  = 'vazir-font-' . $context;

		wp_register_style(
			$handle,
			false,
			$this->get_style_dependencies( $context ),
			$version
		);
		wp_enqueue_style( $handle );

		$this->output_custom_styles( $context, $css );
	}

	/**
	 * Retrieve combined inline CSS for a given context.
	 *
	 * @param string $context Context identifier.
	 * @return string
	 */
	public function get_inline_css( $context ) {
		if ( ! $this->should_load_context( $context ) ) {
			return '';
		}

		$weights = $this->get_active_weights();

		$parts = array(
			self::generate_font_faces( $weights ),
			$this->get_context_css( $context ),
			$this->get_base_font_css( $context ),
		);

		$parts = array_filter(
			array_map(
				static function ( $part ) {
					if ( ! is_string( $part ) ) {
						return '';
					}

					return trim( $part );
				},
				$parts
			)
		);

		if ( empty( $parts ) ) {
			return '';
		}

		return implode( "\n", $parts );
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

		if ( in_array( $context, array( 'frontend', 'admin' ), true ) ) {
			return;
		}

		$handle = 'vazir-font-' . $context;

		wp_register_style(
			$handle,
			false,
			$this->get_style_dependencies( $context ),
			$version
		);
		wp_enqueue_style( $handle );

		$css = self::generate_font_faces( $weights );

		if ( '' !== trim( $css ) ) {
			wp_add_inline_style( $handle, $css );
		}
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
			$css = $this->get_context_css( $context );
		}

		if ( ! is_string( $css ) || '' === trim( $css ) ) {
			return;
		}

		$handle = 'vazir-font-' . $context;

		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_register_style(
				$handle,
				false,
				$this->get_style_dependencies( $context ),
				VAZIR_FONT_VERSION
			);

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
	 * Ensure body class reflects enabled contexts on the frontend.
	 *
	 * @param array<string> $classes Existing body classes.
	 * @return array<string>
	 */
	public function filter_body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}

		$enable_frontend = $this->should_load_context( 'frontend' );
		$enable_gf       = $this->gravityforms_requested && $this->should_load_context( 'gravityforms' );

		if ( $enable_frontend || $enable_gf ) {
			$classes[] = 'vazir-font-enabled';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Append admin body class when enabled.
	 *
	 * @param string $classes Existing admin body classes.
	 * @return string
	 */
	public function filter_admin_body_class( $classes ) {
		if ( ! is_string( $classes ) ) {
			$classes = '';
		}

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
	 * Append login body class when enabled.
	 *
	 * @param array<string> $classes Login body classes.
	 * @return array<string>
	 */
	public function filter_login_body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}

		if ( $this->should_load_context( 'login' ) ) {
			$classes[] = 'vazir-font-enabled';
		}

		return array_values( array_unique( $classes ) );
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
	 * Retrieve style dependencies for the supplied context.
	 *
	 * @param string $context Context identifier.
	 * @return array<string>
	 */
	private function get_style_dependencies( $context ) {
		if ( 'admin' === $context ) {
			return array( 'wp-admin', 'dashicons' );
		}

		return array();
	}

	/**
	 * Retrieve context-specific CSS.
	 *
	 * @param string $context Context identifier.
	 * @return string
	 */
	private function get_context_css( $context ) {
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

		return $css;
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
				'.gform_wrapper .gform_heading',
				'.gform_wrapper .gform_body',
				'.gform_wrapper .gfield',
				'.gform_wrapper .gfield_label',
				'.gform_wrapper .gfield_description',
				'.gform_wrapper .gfield_description p',
				'.gform_wrapper .ginput_container',
				'.gform_wrapper .ginput_container input[type="text"]',
				'.gform_wrapper .ginput_container input[type="email"]',
				'.gform_wrapper .ginput_container input[type="tel"]',
				'.gform_wrapper .ginput_container input[type="url"]',
				'.gform_wrapper .ginput_container input[type="number"]',
				'.gform_wrapper .ginput_container input[type="password"]',
				'.gform_wrapper .ginput_container input[type="search"]',
				'.gform_wrapper .ginput_container input[type="date"]',
				'.gform_wrapper .ginput_container input[type="time"]',
				'.gform_wrapper .ginput_container input[type="datetime-local"]',
				'.gform_wrapper .ginput_container input[type="month"]',
				'.gform_wrapper .ginput_container input[type="week"]',
				'.gform_wrapper .ginput_container textarea',
				'.gform_wrapper .ginput_container select',
				'.gform_wrapper .ginput_container select option',
				'.gform_wrapper .ginput_container select optgroup',
				'.gform_wrapper .ginput_container input::placeholder',
				'.gform_wrapper .ginput_container textarea::placeholder',
				'.gform_wrapper .ginput_container input::-webkit-input-placeholder',
				'.gform_wrapper .ginput_container textarea::-webkit-input-placeholder',
				'.gform_wrapper .ginput_container input::-moz-placeholder',
				'.gform_wrapper .ginput_container textarea::-moz-placeholder',
				'.gform_wrapper .ginput_container input:-ms-input-placeholder',
				'.gform_wrapper .ginput_container textarea:-ms-input-placeholder',
				'.gform_wrapper .ginput_container input:-moz-placeholder',
				'.gform_wrapper .ginput_container textarea:-moz-placeholder',
				'.gform_wrapper .gchoice label',
				'.gform_wrapper .gchoice input[type="checkbox"] + label',
				'.gform_wrapper .gchoice input[type="radio"] + label',
				'.gform_wrapper .ginput_container_consent label',
				'.gform_wrapper .gform_footer',
				'.gform_wrapper .gform_footer input[type="submit"]',
				'.gform_wrapper .gform_footer input[type="button"]',
				'.gform_wrapper .gform_footer button',
				'.gform_wrapper .gform_footer .button',
				'.gform_wrapper .gform_footer .gform_button',
				'.gform_wrapper .gform_page_footer',
				'.gform_wrapper .gform_page_footer .gform_next_button',
				'.gform_wrapper .gform_page_footer .gform_previous_button',
				'.gform_wrapper .gform_page_footer .gform_page_button',
				'.gform_wrapper .gform_page_footer input[type="submit"]',
				'.gform_wrapper .gform_page_footer input[type="button"]',
				'.gform_wrapper .gform_page_footer button',
				'.gform_wrapper .gsection_title',
				'.gform_wrapper .gsection_description',
				'.gform_wrapper .gf_step span',
				'.gform_wrapper .gf_step span.step_label',
				'.gform_wrapper .gf_progressbar',
				'.gform_wrapper .gf_progressbar span',
				'.gform_wrapper .validation_message',
				'.gform_wrapper .validation_error',
				'.gform_wrapper .gfield_validation_message',
				'.gform_wrapper .instruction',
				'.gform_wrapper .gfield_required',
				'.gform_wrapper .gform_save_link',
				'.gform_wrapper .gform_save_message',
				'.gform_wrapper .gform_page .gform_fields li',
				'.gform_wrapper .gform_page_title',
				'.gform_wrapper .gform_page_description',
				'.gform_confirmation_message',
				'.gform_confirmation_wrapper',
				'.gform_wrapper .gform_ajax_spinner',
				'.gform_wrapper .gquiz-container',
				'.gform_wrapper .gform_fileupload_multifile .gform_drop_area',
				'.gform_wrapper .gform_fileupload_multifile .gform_button_select_files',
				'.gform_wrapper .chzn-container',
				'.gform_wrapper .chzn-container .chzn-single',
				'.gform_wrapper .chzn-container .chzn-results li',
				'.gform_wrapper .select2-container',
				'.gform_wrapper .select2-container .select2-selection',
				'.gform_wrapper .select2-container .select2-selection__rendered',
				'.gform_wrapper .select2-container .select2-results__option',
				'.gform_wrapper .gform_wrapper.gravity-theme .gfield_label',
				'.gform_wrapper.gravity-theme .gfield_label',
				'.gform_wrapper.gravity-theme .gfield_description',
				'.gform_wrapper.gravity-theme .ginput_complex span label',
				'.gform_wrapper.gravity-theme .gfield_consent_description',
				'.gform_wrapper.gravity-theme .gform_footer input[type="submit"]',
				'.gform_wrapper.gravity-theme .gform_footer .button',
				'.gform_wrapper.gravity-theme .gform_button',
				'.gform_wrapper.gravity-theme .gform_save_link',
				'.gform_wrapper.gravity-theme .gform_page_footer input',
				'.gform_wrapper.gravity-theme .gform_page_footer .button',
				'.gform_wrapper.gravity-theme .ginput_complex input',
				'.gform_wrapper.gravity-theme .ginput_complex select',
				'.gform_wrapper.gravity-theme .ginput_complex textarea',
				'.gform_wrapper.gravity-theme .ginput_container_address .ginput_full',
				'.gform_wrapper.gravity-theme .ginput_container input[type="text"]',
				'.gform_wrapper.gravity-theme .ginput_container select',
				'.gform_wrapper.gravity-theme .ginput_container textarea',
				'.gform_wrapper.gravity-theme .gfield_list_group',
				'.gform_wrapper.gravity-theme .gfield_list_group input',
				'.gform_wrapper.gravity-theme .gfield_list_group select',
				'.gform_wrapper.gravity-theme .gfield_list_group textarea',
				'.gform_wrapper.gravity-theme .gfield_list_header th',
				'.gform_wrapper.gravity-theme .gfield_list_cell input',
				'.gform_wrapper.gravity-theme .gfield_list_cell select',
				'.gform_wrapper.gravity-theme .gfield_list_cell textarea',
				'.gform_wrapper.gravity-theme .gfield_checkbox label',
				'.gform_wrapper.gravity-theme .gfield_radio label',
				'.gform_wrapper.gravity-theme .ginput_product_price',
				'.gform_wrapper.gravity-theme .ginput_product_total',
				'.gform_wrapper.gravity-theme .ginput_product_price_label',
				'.gform_wrapper.gravity-theme .ginput_product_price_value',
				'.gform_wrapper.gravity-theme .gfield_time_hour input',
				'.gform_wrapper.gravity-theme .gfield_time_minute input',
				'.gform_wrapper.gravity-theme .gfield_time_ampm select',
				'.gform_wrapper.gravity-theme .gform-card',
				'.gform_wrapper.gravity-theme .gform-card__header',
				'.gform_wrapper.gravity-theme .gform-card__body',
				'.gform_wrapper.gravity-theme .gform-card__footer',
				'.gform_wrapper.gravity-theme .gform-field-label',
				'.gform_wrapper.gravity-theme .gform-field-label--type-inline',
				'.gform_wrapper.gravity-theme .gform-fileupload-filename',
				'.gform_wrapper.gravity-theme .gform-field__description',
				'.gform_wrapper.gravity-theme .gform-field__label',
				'.gform_wrapper.gravity-theme .gform-field__input-wrapper',
				'.gform_wrapper.gravity-theme .gform-field__error-message',
				'.gform_wrapper.gravity-theme .gform-field__instruction-message',
			)
		);
	}

	/**
	 * Retrieve the font-family stack for Vazir fonts.
	 *
	 * @return string
	 */
	private function get_font_family() {
		return apply_filters(
			'vazir_font_family',
			"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);
	}

	/**
	 * Build base font CSS for the supplied context.
	 *
	 * @param string $context Context identifier.
	 * @return string
	 */
	private function get_base_font_css( $context ) {
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
				. "}\n"
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
	 * Build CSS rules applying the font stack.
	 *
	 * @param array $exclude_selectors Selectors to exclude.
	 * @param array $base_selectors    Base selectors to target.
	 * @return string
	 */
	private function build_font_css( $exclude_selectors, $base_selectors ) {
		$family = $this->get_font_family();
		$rules  = '';

		foreach ( $base_selectors as $selector ) {
			$scoped = $this->scope_selector( $selector );

			if ( '' === $scoped ) {
				continue;
			}

			$rules .= $scoped . " {\n\tfont-family: {$family};\n}\n";
		}

		if ( ! empty( $exclude_selectors ) ) {
			foreach ( $exclude_selectors as $selector ) {
				$sanitized = sanitize_text_field( $selector );

				if ( '' === $sanitized ) {
					continue;
				}

				$scoped = $this->scope_selector( $sanitized );

				if ( '' === $scoped ) {
					continue;
				}

				$rules .= $scoped . " {\n\tfont-family: inherit;\n}\n";
			}
		}

		if ( is_rtl() ) {
			$rules .= "[dir='rtl'] .vazir-font-enabled {\n\tletter-spacing: normal;\n}\n";
		}

		return $rules;
	}

	/**
	 * Scope a selector to the Vazir font enabled context.
	 *
	 * @param string $selector CSS selector.
	 * @return string
	 */
	private function scope_selector( $selector ) {
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

	/**
	 * Placeholder for regenerating cached font files.
	 */
	private function regenerate_font_files() {
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
