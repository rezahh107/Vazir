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
		return class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_version' );
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
	 * Allowlist our registered style handle in Gravity Forms No Conflict Mode.
	 *
	 * The actual enqueue occurs through admin_enqueue_scripts on Gravity Forms
	 * admin screens; this filter only grants the handle permission to survive
	 * No Conflict Mode.
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
	 * Compatibility workaround retained pending a reproducible regression test.
	 * It removes only inline font-family declarations and leaves other styles.
	 */
	public function remove_inline_font_styles( string $content, $field, $value, $entry_id, $form_id ): string {
		unset( $field, $value, $entry_id, $form_id );
		if ( ! $this->is_enabled() ) {
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
			"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif"
		);

		$css  = VazirFont_Loader::get_instance()->get_font_face_css();
		$css .= "\n.gform-theme--framework {\n\t--gf-font-family-base: {$family};\n}\n";

		// Current/legacy compatibility layer retained until browser proof permits
		// narrower selectors or removal of !important.
		$css .= ".gform_wrapper,\n";
		$css .= ".gform_wrapper .gfield_label,\n";
		$css .= ".gform_wrapper .gfield_description,\n";
		$css .= ".gform_wrapper .ginput_container input,\n";
		$css .= ".gform_wrapper .ginput_container textarea,\n";
		$css .= ".gform_wrapper .ginput_container select,\n";
		$css .= ".gform_wrapper .gform_footer input[type=\"submit\"],\n";
		$css .= ".gform_wrapper .gform_button,\n";
		$css .= ".gform_wrapper .gform_page_footer input {\n";
		$css .= "\tfont-family: {$family} !important;\n";
		$css .= "}\n";

		$this->cached_css = $css;
		return $css;
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
