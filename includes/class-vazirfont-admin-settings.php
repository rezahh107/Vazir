<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the Vazir font admin settings page.
 */
final class VazirFont_Admin_Settings {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Settings page slug.
	 */
	private const PAGE_SLUG = 'vazir-font-settings';

	/**
	 * Allowed font weights.
	 */
	private const ALLOWED_WEIGHTS = [ '300', '400', '500', '700', '900' ];

	/**
	 * Default exclude selectors (fallback).
	 */
	private const DEFAULT_EXCLUDE_SELECTORS = [
		'.dashicons',
		'.menu-icon',
		'.menu-image',
		'[class^="dashicons-"]',
		'[class*=" dashicons-"]',
		'[class^="fa-"]',
		'[class*=" fa-"]',
		'.material-icons',
		'[data-icon]:before',
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
		throw new RuntimeException( 'Cannot unserialize VazirFont_Admin_Settings singleton.' );
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
	 * Register WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'init_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( VAZIR_FONT_PLUGIN_FILE ), [ $this, 'add_settings_link' ] );
	}

	/**
	 * Enqueue admin assets only on our settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'vazir-font-admin', VAZIR_FONT_ASSETS_URL . 'css/admin.css', [], VAZIR_FONT_VERSION );
		wp_enqueue_script( 'vazir-font-admin', VAZIR_FONT_ASSETS_URL . 'js/admin.js', [ 'jquery' ], VAZIR_FONT_VERSION, true );
		wp_localize_script(
			'vazir-font-admin',
			'vazirFontAdminL10n',
			[
				'confirmReset' => __( 'Are you sure you want to reset settings?', 'vazir-font-wp' ),
			]
		);
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'تنظیمات فونت وزیر', 'vazir-font-wp' ),
			__( 'فونت وزیر', 'vazir-font-wp' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function init_settings(): void {
		register_setting(
			'vazir_font_settings',
			VAZIR_FONT_OPTION_NAME,
			[ $this, 'sanitize_options' ]
		);

		// General section.
		add_settings_section(
			'vazir_font_general',
			__( 'تنظیمات عمومی', 'vazir-font-wp' ),
			[ $this, 'render_general_section_desc' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'enable_frontend',
			__( 'فعال‌سازی در فرانت‌اند', 'vazir-font-wp' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'vazir_font_general',
			[
				'name'  => 'enable_frontend',
				'label' => __( 'فونت وزیر در تمام صفحات سایت اعمال شود', 'vazir-font-wp' ),
			]
		);

		add_settings_field(
			'enable_admin',
			__( 'فعال‌سازی در پنل مدیریت', 'vazir-font-wp' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'vazir_font_general',
			[
				'name'  => 'enable_admin',
				'label' => __( 'فونت وزیر در پنل مدیریت وردپرس اعمال شود', 'vazir-font-wp' ),
			]
		);

		add_settings_field(
			'enable_gravity_forms',
			__( 'فعال‌سازی در گرویتی فرمز', 'vazir-font-wp' ),
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'vazir_font_general',
			[
				'name'  => 'enable_gravity_forms',
				'label' => __( 'فونت وزیر در فرم‌های گرویتی فرمز اعمال شود', 'vazir-font-wp' ),
			]
		);

		// Font weights section.
		add_settings_section(
			'vazir_font_weights',
			__( 'وزن‌های فونت', 'vazir-font-wp' ),
			[ $this, 'render_weights_section_desc' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'font_weights',
			__( 'وزن‌های مورد استفاده', 'vazir-font-wp' ),
			[ $this, 'render_weights_field' ],
			self::PAGE_SLUG,
			'vazir_font_weights'
		);

		// Advanced section.
		add_settings_section(
			'vazir_font_advanced',
			__( 'تنظیمات پیشرفته', 'vazir-font-wp' ),
			[ $this, 'render_advanced_section_desc' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'exclude_selectors',
			__( 'استثناء انتخابگرها', 'vazir-font-wp' ),
			[ $this, 'render_textarea_field' ],
			self::PAGE_SLUG,
			'vazir_font_advanced',
			[
				'name'        => 'exclude_selectors',
				'description' => __( 'انتخابگرهای CSS که نباید فونت وزیر روی آن‌ها اعمال شود (هر کدام در خط جداگانه)', 'vazir-font-wp' ),
			]
		);
	}

	/**
	 * Render the settings page markup.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'vazir-font-wp' ) );
		}
		?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="vazir-font-admin-header">
		<p><?php esc_html_e( 'این افزونه فونت وزیر را به تمام بخش‌های وردپرس شما اضافه می‌کند.', 'vazir-font-wp' ); ?></p>
	</div>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'vazir_font_settings' );
		do_settings_sections( self::PAGE_SLUG );
		submit_button( __( 'ذخیره تنظیمات', 'vazir-font-wp' ) );
		?>
	</form>

	<div class="vazir-font-preview">
		<h3><?php esc_html_e( 'پیش‌نمایش فونت', 'vazir-font-wp' ); ?></h3>
		<div class="vazir-font-preview__text">
			<?php
			$weights = [
				'300' => __( '300 (Light)', 'vazir-font-wp' ),
				'400' => __( '400 (Regular)', 'vazir-font-wp' ),
				'500' => __( '500 (Medium)', 'vazir-font-wp' ),
				'700' => __( '700 (Bold)', 'vazir-font-wp' ),
				'900' => __( '900 (Black)', 'vazir-font-wp' ),
			];

			foreach ( $weights as $weight => $label ) {
				printf(
					'<p style="font-family: \'Vazir\', sans-serif; font-size: 16px; font-weight: %1$s;">%2$s</p>',
					esc_attr( $weight ),
					esc_html( $label )
				);
			}
			?>
		</div>
	</div>
</div>
		<?php
	}

	/**
	 * Sanitize plugin options.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_options( $input ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->log_security_event( 'Unauthorized settings update blocked.', 'critical' );
			wp_die( esc_html__( 'Unauthorized access.', 'vazir-font-wp' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'vazir_font_settings-options' ) ) {
			$this->log_security_event( 'Nonce verification failed during settings save.', 'warning' );
			add_settings_error(
				VAZIR_FONT_OPTION_NAME,
				'nonce_failed',
				esc_html__( 'Security verification failed.', 'vazir-font-wp' ),
				'error'
			);
			return VazirFontPlugin::get_options();
		}

		$current_user_id = get_current_user_id();
		$transient_key   = 'vazir_font_save_count_' . ( $current_user_id ?: 'guest' );
		$save_count      = (int) get_transient( $transient_key );

		if ( $save_count > 10 ) {
			$this->log_security_event( 'Rate limit triggered for settings save.', 'warning' );
			add_settings_error(
				VAZIR_FONT_OPTION_NAME,
				'rate_limit',
				esc_html__( 'Too many save attempts. Please wait a minute.', 'vazir-font-wp' ),
				'error'
			);
			return VazirFontPlugin::get_options();
		}
		set_transient( $transient_key, $save_count + 1, MINUTE_IN_SECONDS );

		$current_options = VazirFontPlugin::get_options();
		$sanitized       = [];

		// Checkbox fields.
		$checkboxes = [ 'enable_frontend', 'enable_admin', 'enable_gravity_forms' ];
		foreach ( $checkboxes as $checkbox ) {
			$value = false;
			if ( isset( $input[ $checkbox ] ) ) {
				$filtered = filter_var( $input[ $checkbox ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				$value    = ( null === $filtered ) ? false : (bool) $filtered;
			}
			$sanitized[ $checkbox ] = $value;
		}

		// Font weights.
		if ( isset( $input['font_weights'] ) && is_array( $input['font_weights'] ) ) {
			$selected = array_map( 'sanitize_text_field', $input['font_weights'] );
			$valid    = array_values( array_intersect( $selected, self::ALLOWED_WEIGHTS ) );
			if ( empty( $valid ) ) {
				$valid = [ '400' ];
				add_settings_error(
					VAZIR_FONT_OPTION_NAME,
					'no_weights_selected',
					esc_html__( 'At least one font weight must be selected. Weight 400 was enabled automatically.', 'vazir-font-wp' ),
					'warning'
				);
				$this->log_security_event( 'No font weights selected; defaulted to 400.', 'notice' );
			}
			$sanitized['font_weights'] = $valid;
		} else {
			$sanitized['font_weights'] = $current_options['font_weights'] ?? [ '400' ];
		}

		// Exclude selectors.
		$sanitized['exclude_selectors'] = $current_options['exclude_selectors'] ?? self::DEFAULT_EXCLUDE_SELECTORS;
		if ( isset( $input['exclude_selectors'] ) && is_string( $input['exclude_selectors'] ) ) {
			$raw_lines = explode( "\n", $input['exclude_selectors'] );
			$raw_lines = array_map( 'trim', $raw_lines );
			$raw_lines = array_filter( $raw_lines );

			$clean = [];
			foreach ( $raw_lines as $selector ) {
				$sanitized_selector = $this->sanitize_css_selector( $selector );
				if ( '' === $sanitized_selector ) {
					$this->log_security_event( sprintf( 'CSS selector rejected during sanitization: %s', $selector ), 'critical' );
					continue;
				}
				$validated = $this->validate_css_selector( $sanitized_selector );
				if ( '' === $validated ) {
					$this->log_security_event( sprintf( 'CSS selector failed validation: %s', $selector ), 'critical' );
					continue;
				}
				$clean[] = $validated;
			}
			$clean = array_values( array_unique( $clean ) );
			$sanitized['exclude_selectors'] = array_slice( $clean, 0, 50 );
		}

		if ( $sanitized !== $current_options ) {
			VazirFontPlugin::clear_cache();
		}

		return $sanitized;
	}

	/**
	 * Sanitize a CSS selector.
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
	 * Validate a sanitized CSS selector.
	 */
	private function validate_css_selector( string $selector ): string {
		if ( '' === $selector ) {
			return '';
		}
		if ( strpos( $selector, '{' ) !== false || strpos( $selector, '}' ) !== false || strpos( $selector, ';' ) !== false ) {
			return '';
		}
		if ( strpos( $selector, '/*' ) !== false ) {
			return '';
		}
		if ( ! preg_match( '/^[a-zA-Z.#]/', $selector ) ) {
			return '';
		}
		if ( ! preg_match( '/^[a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~]+$/', $selector ) ) {
			return '';
		}
		$invalid = [ '##', '..', ',,', '>>', '++', '~~', '**' ];
		foreach ( $invalid as $seq ) {
			if ( strpos( $selector, $seq ) !== false ) {
				return '';
			}
		}
		return $selector;
	}

	/**
	 * Log security events when debugging is enabled.
	 */
	private function log_security_event( string $event, string $severity = 'warning' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[Vazir Font Security] [%s] User %d: %s',
				$severity,
				get_current_user_id(),
				$event
			) );
		}
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section_desc(): void {
		echo '<p>' . esc_html__( 'انتخاب کنید فونت در کدام بخش‌ها فعال باشد.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * Render weights section description.
	 */
	public function render_weights_section_desc(): void {
		echo '<p>' . esc_html__( 'وزن‌های مورد نیاز را انتخاب کنید تا فقط فونت‌های ضروری بارگذاری شوند.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 */
	public function render_advanced_section_desc(): void {
		echo '<p>' . esc_html__( 'انتخابگرهایی که باید از اعمال فونت مستثنی شوند را تعیین کنید.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_checkbox_field( array $args ): void {
		$options = VazirFontPlugin::get_options();
		$name    = $args['name'];
		$label   = $args['label'];
		$id      = 'vazir-font-' . sanitize_key( $name );
		$checked = ! empty( $options[ $name ] );

		echo '<fieldset>';
		echo '<label for="' . esc_attr( $id ) . '">';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( VAZIR_FONT_OPTION_NAME ) . '[' . esc_attr( $name ) . ']" value="1" ' . checked( $checked, true, false ) . ' />';
		echo ' ' . esc_html( $label );
		echo '</label>';
		echo '</fieldset>';
	}

	/**
	 * Render font weights checkboxes.
	 */
	public function render_weights_field(): void {
		$options  = VazirFontPlugin::get_options();
		$selected = $options['font_weights'] ?? [ '400' ];
		if ( ! is_array( $selected ) ) {
			$selected = [ '400' ];
		}
		$weights = [
			'300' => __( '300 (Light)', 'vazir-font-wp' ),
			'400' => __( '400 (Regular)', 'vazir-font-wp' ),
			'500' => __( '500 (Medium)', 'vazir-font-wp' ),
			'700' => __( '700 (Bold)', 'vazir-font-wp' ),
			'900' => __( '900 (Black)', 'vazir-font-wp' ),
		];

		echo '<fieldset>';
		foreach ( $weights as $weight => $label ) {
			$id      = 'vazir-font-weight-' . $weight;
			$checked = in_array( $weight, $selected, true );
			echo '<label for="' . esc_attr( $id ) . '" class="vazir-font-weight-option">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( VAZIR_FONT_OPTION_NAME ) . '[font_weights][]" value="' . esc_attr( $weight ) . '" ' . checked( $checked, true, false ) . ' /> ';
			echo esc_html( $label );
			echo '</label><br />';
		}
		echo '</fieldset>';
	}

	/**
	 * Render a textarea field.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_textarea_field( array $args ): void {
		$options     = VazirFontPlugin::get_options();
		$name        = $args['name'];
		$description = $args['description'] ?? '';
		$id          = 'vazir-font-' . sanitize_key( $name );
		$value       = isset( $options[ $name ] ) && is_array( $options[ $name ] ) ? implode( "\n", $options[ $name ] ) : '';

		echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'استثناء انتخابگرها', 'vazir-font-wp' ) . '</label>';
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( VAZIR_FONT_OPTION_NAME ) . '[' . esc_attr( $name ) . ']" rows="6" cols="50" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $description ) ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Add a settings link on the plugins screen.
	 *
	 * @param array<string> $links Existing links.
	 * @return array<string>
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'تنظیمات', 'vazir-font-wp' ) . '</a>';
		$links[]       = $settings_link;
		return $links;
	}
}
