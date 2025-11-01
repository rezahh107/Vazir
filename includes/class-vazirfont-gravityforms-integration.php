<?php
/**
 * Gravity Forms integration for Vazir font plugin.
 *
 * @package Vazir_Font_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides Gravity Forms integration for Vazir font.
 *
 * @package Vazir_Font_WP
 */
class VazirFont_GravityForms_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var VazirFont_GravityForms_Integration|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return VazirFont_GravityForms_Integration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook registration.
	 */
	private function __construct() {
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_gravityforms_assets' ), 999, 2 );
		add_action( 'gform_preview_init', array( $this, 'mark_preview_request' ) );
		add_action( 'current_screen', array( $this, 'maybe_flag_admin_screen' ) );
		add_action( 'admin_head', array( $this, 'maybe_add_admin_styles' ), 999 );
		add_filter( 'gform_preview_styles', array( $this, 'filter_preview_styles' ), 10, 3 );
		add_filter( 'gform_field_content', array( $this, 'remove_inline_font_styles' ), 999, 5 );
	}

	/**
	 * Enqueue fonts for Gravity Forms when enabled.
	 */
	public function enqueue_gravityforms_assets( $form = array(), $is_ajax = false ) {
		unset( $form, $is_ajax );

		$options = VazirFontPlugin::get_options();

		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();

		if ( ! has_action( 'wp_head', array( $loader, 'add_gravityforms_styles' ) ) ) {
			add_action( 'wp_head', array( $loader, 'add_gravityforms_styles' ), 25 );
		}
	}

	/**
	 * Flag Gravity Forms admin screens early to ensure body classes are applied.
	 */
	public function maybe_flag_admin_screen( $screen = null ) {
		$options = VazirFontPlugin::get_options();

		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		if ( null === $screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		if ( ! $screen || ! is_object( $screen ) ) {
			return;
		}

		if ( false === strpos( $screen->id, 'gf_' ) && false === strpos( $screen->id, 'gravityforms' ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();
	}

	/**
	 * Append Vazir styles within Gravity Forms admin pages.
	 */
	public function maybe_add_admin_styles() {
		if ( ! is_admin() ) {
			return;
		}

		$options = VazirFontPlugin::get_options();

		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! is_object( $screen ) ) {
			return;
		}

		if ( false === strpos( $screen->id, 'gf_' ) && false === strpos( $screen->id, 'gravityforms' ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();
		$loader->add_gf_admin_styles();
	}

	/**
	 * Add Vazir styles into the Gravity Forms preview output.
	 *
	 * @param string $styles Existing preview styles.
	 * @param array  $form   Current form.
	 * @param mixed  $lead   Submitted entry or null.
	 * @return string
	 */
	public function filter_preview_styles( $styles, $form, $lead = null ) {
		unset( $form, $lead );

		$options = VazirFontPlugin::get_options();

		if ( empty( $options['enable_gravity_forms'] ) ) {
			return $styles;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();

		$css = $loader->get_inline_css( 'gravityforms' );

		if ( '' === $css ) {
			return $styles;
		}

		$styles = trim( (string) $styles );

		if ( '' !== $styles ) {
			$styles .= "\n";
		}

		return $styles . $css;
	}

	/**
	 * Ensure preview requests receive the Vazir font.
	 */
	public function mark_preview_request() {
		$options = VazirFontPlugin::get_options();

		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();
	}

	/**
	 * Remove inline font-family declarations injected by Gravity Forms fields.
	 *
	 * @param string     $content Field content HTML.
	 * @param GF_Field   $field   Field instance.
	 * @param mixed      $value   Current value.
	 * @param int|string $lead_id Entry ID.
	 * @param int        $form_id Form ID.
	 * @return string
	 */
	public function remove_inline_font_styles( $content, $field, $value, $lead_id, $form_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $field, $value, $lead_id, $form_id );

		$filtered = preg_replace(
			'/style=(["\'])(.*?)font-family:[^;"\'>]*;?(.*?)\1/i',
			'style=$1$2$3$1',
			$content
		);

		return is_string( $filtered ) ? $filtered : $content;
	}
}
