<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Forms integration for Vazir font plugin.
 */
final class VazirFont_GravityForms_Integration {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

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
		throw new RuntimeException( 'Cannot unserialize VazirFont_GravityForms_Integration singleton.' );
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
	 * Register Gravity Forms hooks.
	 */
	private function init_hooks(): void {
		add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_gravityforms_assets' ], 999, 2 );
		add_action( 'gform_preview_init', [ $this, 'mark_preview_request' ] );
		add_action( 'current_screen', [ $this, 'maybe_flag_admin_screen' ] );
		add_action( 'admin_head', [ $this, 'maybe_add_admin_styles' ], 999 );
		add_filter( 'gform_preview_styles', [ $this, 'filter_preview_styles' ], 10, 3 );
		add_filter( 'gform_field_content', [ $this, 'remove_inline_font_styles' ], 999, 5 );
	}

	/**
	 * Enqueue fonts for Gravity Forms on the frontend.
	 *
	 * @param array $form    Form data.
	 * @param bool  $is_ajax Whether the request is AJAX.
	 */
	public function enqueue_gravityforms_assets( $form = [], $is_ajax = false ): void {
		unset( $form, $is_ajax );

		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();

		// Output inline CSS for Gravity Forms in the frontend head.
		if ( ! has_action( 'wp_head', [ $this, 'output_gravityforms_css' ] ) ) {
			add_action( 'wp_head', [ $this, 'output_gravityforms_css' ], 25 );
		}
	}

	/**
	 * Output Gravity Forms inline CSS in the frontend.
	 */
	public function output_gravityforms_css(): void {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$css    = $loader->get_inline_css( 'gravityforms' );
		if ( '' === trim( $css ) ) {
			return;
		}

		echo '<style id="vazir-font-gravityforms-inline-css">' . "\n" . $css . "\n</style>\n";
	}

	/**
	 * Output Gravity Forms inline CSS in the admin area.
	 */
	public function output_gf_admin_css(): void {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$css    = $loader->get_inline_css( 'gravityforms' );
		if ( '' === trim( $css ) ) {
			return;
		}

		echo '<style id="vazir-font-gravityforms-admin-css">' . "\n" . $css . "\n</style>\n";
	}

	/**
	 * Flag Gravity Forms admin screens early to ensure body classes are applied.
	 *
	 * @param ?WP_Screen $screen Current screen object.
	 */
	public function maybe_flag_admin_screen( $screen = null ): void {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		if ( null === $screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		if ( strpos( $screen->id, 'gf_' ) === false && strpos( $screen->id, 'gravityforms' ) === false ) {
			return;
		}

		VazirFont_Loader::get_instance()->mark_gravityforms_request();
	}

	/**
	 * Append Vazir styles within Gravity Forms admin pages.
	 */
	public function maybe_add_admin_styles(): void {
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
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		if ( strpos( $screen->id, 'gf_' ) === false && strpos( $screen->id, 'gravityforms' ) === false ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();

		// Output admin styles.
		$this->output_gf_admin_css();
	}

	/**
	 * Add Vazir styles into the Gravity Forms preview output.
	 *
	 * @param array|string $styles Existing preview styles.
	 * @param array        $form   Current form.
	 * @param mixed        $lead   Submitted entry or null.
	 * @return array
	 */
	public function filter_preview_styles( $styles, array $form, $lead = null ): array {
		unset( $form, $lead );

		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return is_array( $styles ) ? $styles : ( is_string( $styles ) ? [ $styles ] : [] );
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->mark_gravityforms_request();

		$css = $loader->get_inline_css( 'gravityforms' );
		if ( '' === trim( $css ) ) {
			return is_array( $styles ) ? $styles : ( is_string( $styles ) ? [ $styles ] : [] );
		}

		// Normalize $styles to an array.
		if ( is_string( $styles ) ) {
			$styles = [ $styles ];
		} elseif ( ! is_array( $styles ) ) {
			$styles = [];
		}

		$styles[] = $css;
		return $styles;
	}

	/**
	 * Mark preview request for Gravity Forms.
	 */
	public function mark_preview_request(): void {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}
		VazirFont_Loader::get_instance()->mark_gravityforms_request();
	}

	/**
	 * Remove inline font-family declarations injected by Gravity Forms fields.
	 *
	 * @param string   $content Field content HTML.
	 * @param GF_Field $field   Field instance.
	 * @param mixed    $value   Current value.
	 * @param int      $lead_id Entry ID.
	 * @param int      $form_id Form ID.
	 * @return string
	 */
	public function remove_inline_font_styles( string $content, $field, $value, $lead_id, $form_id ): string {
		unset( $field, $value, $lead_id, $form_id );

		$filtered = preg_replace(
			'/style=(["\'])(.*?)font-family:[^;"\'>]*;?(.*?)\1/i',
			'style=$1$2$3$1',
			$content
		);

		return is_string( $filtered ) ? $filtered : $content;
	}
}
