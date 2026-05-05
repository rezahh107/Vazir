<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Forms integration for Vazir font plugin.
 * Uses modern CSS API (variables) for reliable theming and adds
 * compatibility with multi‑page forms, no‑conflict mode, and field‑level classes.
 */
final class VazirFont_GravityForms_Integration {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Whether Gravity Forms is active and usable.
	 */
	private bool $gf_available = false;

	/**
	 * Cached modern CSS string (to avoid regenerating many times per request).
	 */
	private ?string $cached_modern_css = null;

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Check if Gravity Forms is available.
		$this->gf_available = $this->is_gravity_forms_active();

		if ( ! $this->gf_available ) {
			return; // No hooks registered if GF is not present.
		}

		$this->init_hooks();
	}

	/**
	 * Check if Gravity Forms is installed and active.
	 */
	private function is_gravity_forms_active(): bool {
		return class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_version' );
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
	 * Register Gravity Forms hooks (only if GF is available).
	 */
	private function init_hooks(): void {
		// Frontend enqueue.
		add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_gravityforms_assets' ], 999, 2 );

		// Preview & admin screens.
		add_action( 'gform_preview_init', [ $this, 'mark_preview_request' ] );
		add_action( 'current_screen', [ $this, 'maybe_flag_admin_screen' ] );
		add_action( 'admin_head', [ $this, 'maybe_add_admin_styles' ], 999 );

		// Preview styles filter.
		add_filter( 'gform_preview_styles', [ $this, 'filter_preview_styles' ], 10, 3 );

		// Remove inline font styles injected by GF.
		add_filter( 'gform_field_content', [ $this, 'remove_inline_font_styles' ], 999, 5 );

		// Modern: no‑conflict styles – ensure our CSS loads in GF admin editors.
		add_filter( 'gform_noconflict_styles', [ $this, 'add_noconflict_styles' ] );

		// Modern: add a custom CSS class to every field for finer control.
		add_filter( 'gform_field_css_class', [ $this, 'add_field_css_class' ], 10, 3 );

		// Modern: support for multi‑page forms (re‑apply class after page navigation).
		add_action( 'gform_post_render', [ $this, 'maybe_print_multipage_script' ], 10, 2 );
	}

	/**
	 * Generate modern CSS using Gravity Forms CSS API (variables).
	 * This method is cached per request for performance.
	 */
	private function get_gf_modern_css(): string {
		if ( null !== $this->cached_modern_css ) {
			return $this->cached_modern_css;
		}

		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			$this->cached_modern_css = '';
			return '';
		}

		$family = apply_filters(
			'vazir_font_family',
			"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);

		// Get @font-face declarations from loader.
		$loader = VazirFont_Loader::get_instance();
		$css    = $loader->generate_font_faces( $loader->get_selected_weights() );

		// Modern CSS variables (GF 2.5+).
		$css .= "\n.gform-theme--framework {\n";
		$css .= "\t--gf-ctrl-font-family: {$family};\n";
		$css .= "\t--gf-ctrl-label-font-family: {$family};\n";
		$css .= "\t--gf-ctrl-btn-font-family: {$family};\n";
		$css .= "\t--gf-ctrl-choice-checked-font-family: {$family};\n";
		$css .= "}\n";

		// Fallback for legacy forms (pre‑GF 2.5).
		$css .= ".gform_wrapper .gform_body,\n";
		$css .= ".gform_wrapper .gfield_label,\n";
		$css .= ".gform_wrapper .ginput_container input,\n";
		$css .= ".gform_wrapper .ginput_container textarea,\n";
		$css .= ".gform_wrapper .ginput_container select,\n";
		$css .= ".gform_wrapper .gform_footer input[type=\"submit\"] {\n";
		$css .= "\tfont-family: {$family} !important;\n";
		$css .= "}\n";

		// Safety: remove any HTML tags that might have slipped in.
		$css = wp_strip_all_tags( $css );

		$this->cached_modern_css = $css;
		return $css;
	}

	/**
	 * Output modern CSS for frontend Gravity Forms.
	 */
	public function output_modern_gf_css(): void {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$css = $this->get_gf_modern_css();
		if ( '' === trim( $css ) ) {
			return;
		}

		echo '<style id="vazir-font-gf-modern-css">' . "\n" . $css . "\n</style>\n";
	}

	/**
	 * Output admin styles (same modern CSS) for GF admin pages.
	 */
	public function output_gf_admin_modern_css(): void {
		$this->output_modern_gf_css();
	}

	/**
	 * Enqueue assets for Gravity Forms on the frontend.
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

		if ( ! has_action( 'wp_head', [ $this, 'output_modern_gf_css' ] ) ) {
			add_action( 'wp_head', [ $this, 'output_modern_gf_css' ], 26 );
		}
	}

	/**
	 * Add our style handles to the no‑conflict list.
	 *
	 * @param array<string> $styles Existing style handles.
	 * @return array<string>
	 */
	public function add_noconflict_styles( array $styles ): array {
		$styles[] = 'vazir-font-gf-modern-css';
		return $styles;
	}

	/**
	 * Add a custom CSS class to every field.
	 *
	 * @param string   $css_class Existing classes.
	 * @param GF_Field $field     Field object.
	 * @param array    $form      Form array.
	 * @return string
	 */
	public function add_field_css_class( string $css_class, $field, array $form ): string {
		unset( $field, $form );
		$options = VazirFontPlugin::get_options();
		if ( ! empty( $options['enable_gravity_forms'] ) ) {
			$css_class .= ' vazir-font-enabled-field';
		}
		return trim( $css_class );
	}

	/**
	 * For multi‑page forms, ensure the CSS class is re‑applied after AJAX navigation.
	 *
	 * @param array $form        Form array.
	 * @param bool  $is_ajax_call Whether this is an AJAX call.
	 */
	public function maybe_print_multipage_script( array $form, bool $is_ajax_call ): void {
		unset( $form );
		if ( ! $is_ajax_call ) {
			return;
		}

		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}
		?>
		<script type="text/javascript">
			window.addEventListener('gform_page_loaded', function() {
				if (!document.body.classList.contains('vazir-font-enabled')) {
					document.body.classList.add('vazir-font-enabled');
				}
			});
		</script>
		<?php
	}

	/**
	 * Mark preview request (GF preview init).
	 */
	public function mark_preview_request(): void {
		$options = VazirFontPlugin::get_options();
		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}
		VazirFont_Loader::get_instance()->mark_gravityforms_request();
	}

	/**
	 * Flag GF admin screens early.
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
	 * Add styles to GF admin pages.
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

		VazirFont_Loader::get_instance()->mark_gravityforms_request();
		$this->output_gf_admin_modern_css();
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
			// Normalize $styles to array anyway.
			return is_array( $styles ) ? $styles : ( is_string( $styles ) ? [ $styles ] : [] );
		}

		VazirFont_Loader::get_instance()->mark_gravityforms_request();

		// Get modern CSS (already includes @font-face and variables).
		$css = $this->get_gf_modern_css();
		if ( '' === trim( $css ) ) {
			return is_array( $styles ) ? $styles : ( is_string( $styles ) ? [ $styles ] : [] );
		}

		// Normalize $styles to array.
		if ( is_string( $styles ) ) {
			$styles = [ $styles ];
		} elseif ( ! is_array( $styles ) ) {
			$styles = [];
		}
		$styles[] = $css;
		return $styles;
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
