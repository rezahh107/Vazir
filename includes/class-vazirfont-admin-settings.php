<?php
/**
 * Admin settings controller for the Vazir font plugin.
 *
 * @package Vazir_Font_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Vazir font admin settings page.
 *
 * @package Vazir_Font_WP
 */
class VazirFont_Admin_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var VazirFont_Admin_Settings|null
	 */
	private static $instance = null;

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'vazir-font-settings';

	/**
	 * Retrieve singleton instance.
	 *
	 * @return VazirFont_Admin_Settings
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
		$this->init_hooks();
	}

	/**
	 * Attach WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VAZIR_FONT_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Enqueue admin assets for the plugin settings page.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_' . $this->page_slug !== $hook ) {
			return;
		}

		wp_enqueue_style( 'vazir-font-admin', VAZIR_FONT_ASSETS_URL . 'css/admin.css', array(), VAZIR_FONT_VERSION );
		wp_enqueue_script( 'vazir-font-admin', VAZIR_FONT_ASSETS_URL . 'js/admin.js', array( 'jquery' ), VAZIR_FONT_VERSION, true );
		wp_localize_script(
			'vazir-font-admin',
			'vazirFontAdminL10n',
			array(
				'confirmReset' => __( 'Are you sure you want to reset settings?', 'vazir-font-wp' ),
			)
		);
	}

	/**
	 * Register the settings page entry.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'تنظیمات فونت وزیر', 'vazir-font-wp' ),
			__( 'فونت وزیر', 'vazir-font-wp' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function init_settings() {
		register_setting(
			'vazir_font_settings',
			'vazir_font_options',
			array( $this, 'sanitize_options' )
		);

		// General section.
		add_settings_section(
			'vazir_font_general',
			__( 'تنظیمات عمومی', 'vazir-font-wp' ),
			array( $this, 'render_general_section_desc' ),
			$this->page_slug
		);

		add_settings_field(
			'enable_frontend',
			__( 'فعال‌سازی در فرانت‌اند', 'vazir-font-wp' ),
			array( $this, 'render_checkbox_field' ),
			$this->page_slug,
			'vazir_font_general',
			array(
				'name'  => 'enable_frontend',
				'label' => __( 'فونت وزیر در تمام صفحات سایت اعمال شود', 'vazir-font-wp' ),
			)
		);

		add_settings_field(
			'enable_admin',
			__( 'فعال‌سازی در پنل مدیریت', 'vazir-font-wp' ),
			array( $this, 'render_checkbox_field' ),
			$this->page_slug,
			'vazir_font_general',
			array(
				'name'  => 'enable_admin',
				'label' => __( 'فونت وزیر در پنل مدیریت وردپرس اعمال شود', 'vazir-font-wp' ),
			)
		);

		add_settings_field(
			'enable_gravity_forms',
			__( 'فعال‌سازی در گرویتی فرمز', 'vazir-font-wp' ),
			array( $this, 'render_checkbox_field' ),
			$this->page_slug,
			'vazir_font_general',
			array(
				'name'  => 'enable_gravity_forms',
				'label' => __( 'فونت وزیر در فرم‌های گرویتی فرمز اعمال شود', 'vazir-font-wp' ),
			)
		);

		// Font weights section.
		add_settings_section(
			'vazir_font_weights',
			__( 'وزن‌های فونت', 'vazir-font-wp' ),
			array( $this, 'render_weights_section_desc' ),
			$this->page_slug
		);

		add_settings_field(
			'font_weights',
			__( 'وزن‌های مورد استفاده', 'vazir-font-wp' ),
			array( $this, 'render_weights_field' ),
			$this->page_slug,
			'vazir_font_weights'
		);

		// Advanced section.
		add_settings_section(
			'vazir_font_advanced',
			__( 'تنظیمات پیشرفته', 'vazir-font-wp' ),
			array( $this, 'render_advanced_section_desc' ),
			$this->page_slug
		);

		add_settings_field(
			'exclude_selectors',
			__( 'استثناء انتخابگرها', 'vazir-font-wp' ),
			array( $this, 'render_textarea_field' ),
			$this->page_slug,
			'vazir_font_advanced',
			array(
				'name'        => 'exclude_selectors',
				'description' => __( 'انتخابگرهای CSS که نباید فونت وزیر روی آن‌ها اعمال شود (هر کدام در خط جداگانه)', 'vazir-font-wp' ),
			)
		);
	}

	/**
	 * Render the settings page markup.
	 */
	public function render_settings_page() {
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
		do_settings_sections( $this->page_slug );
		submit_button( __( 'ذخیره تنظیمات', 'vazir-font-wp' ) );
		?>
</form>

<div class="vazir-font-preview">
<h3><?php esc_html_e( 'پیش‌نمایش فونت', 'vazir-font-wp' ); ?></h3>
<div class="vazir-font-preview__text">
		<?php
		$weights = array(
			'300' => __( '300 (Light)', 'vazir-font-wp' ),
			'400' => __( '400 (Regular)', 'vazir-font-wp' ),
			'500' => __( '500 (Medium)', 'vazir-font-wp' ),
			'700' => __( '700 (Bold)', 'vazir-font-wp' ),
			'900' => __( '900 (Black)', 'vazir-font-wp' ),
		);

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
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->log_security_event( 'Unauthorized settings update blocked.', 'critical' );
			wp_die( esc_html__( 'Unauthorized access.', 'vazir-font-wp' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'vazir_font_settings-options' ) ) {
			$this->log_security_event( 'Nonce verification failed during settings save.', 'warning' );
			add_settings_error(
				'vazir_font_options',
				'nonce_failed',
				esc_html__( 'Security verification failed.', 'vazir-font-wp' ),
				'error'
			);

			return VazirFontPlugin::get_options();
		}

		$current_user_id = get_current_user_id();
		$transient_key   = 'vazir_font_save_count_' . ( $current_user_id ? $current_user_id : 'guest' );
		$save_count      = (int) get_transient( $transient_key );

		if ( $save_count > 10 ) {
			$this->log_security_event( 'Rate limit triggered for settings save.', 'warning' );
			add_settings_error(
				'vazir_font_options',
				'rate_limit',
				esc_html__( 'Too many save attempts. Please wait a minute.', 'vazir-font-wp' ),
				'error'
			);

			return VazirFontPlugin::get_options();
		}

		set_transient( $transient_key, $save_count + 1, MINUTE_IN_SECONDS );

		$sanitized       = array();
		$current_options = VazirFontPlugin::get_options();

		$checkboxes = array( 'enable_frontend', 'enable_admin', 'enable_gravity_forms' );
		foreach ( $checkboxes as $checkbox ) {
			$value = false;

			if ( isset( $input[ $checkbox ] ) ) {
				$filtered = filter_var( $input[ $checkbox ], FILTER_VALIDATE_BOOLEAN, array( 'flags' => FILTER_NULL_ON_FAILURE ) );
				$value    = ( null === $filtered ) ? false : (bool) $filtered;
			}

			$sanitized[ $checkbox ] = $value;
		}

		if ( isset( $input['font_weights'] ) && is_array( $input['font_weights'] ) ) {
			$allowed_weights  = array( '300', '400', '500', '700', '900' );
			$selected_weights = array_map( 'sanitize_text_field', $input['font_weights'] );

			$valid_weights = array();

			foreach ( $selected_weights as $weight ) {
				if ( in_array( $weight, $allowed_weights, true ) ) {
					$valid_weights[] = $weight;
				}
			}

			$valid_weights = array_values( array_unique( $valid_weights ) );

			if ( empty( $valid_weights ) ) {
				$valid_weights[] = '400';
				add_settings_error(
					'vazir_font_options',
					'no_weights_selected',
					esc_html__( 'At least one font weight must be selected. Weight 400 was enabled automatically.', 'vazir-font-wp' ),
					'warning'
				);
				$this->log_security_event( 'No font weights selected; defaulted to 400.', 'notice' );
			}
			$sanitized['font_weights'] = $valid_weights;
		} else {
			$sanitized['font_weights'] = isset( $current_options['font_weights'] ) ? (array) $current_options['font_weights'] : array( '400' );
		}

		$sanitized['exclude_selectors'] = isset( $current_options['exclude_selectors'] ) ? (array) $current_options['exclude_selectors'] : array();

		if ( isset( $input['exclude_selectors'] ) ) {
			$raw_selectors = explode( "\n", (string) $input['exclude_selectors'] );
			$raw_selectors = array_map( 'trim', $raw_selectors );
			$raw_selectors = array_filter( $raw_selectors );

			$clean_selectors = array();

			foreach ( $raw_selectors as $selector ) {
				$sanitized_selector = $this->sanitize_css_selector( $selector );

				if ( '' === $sanitized_selector ) {
					$this->log_security_event( sprintf( 'CSS selector rejected during sanitization: %s', $selector ), 'critical' );
					continue;
				}

				$validated_selector = $this->validate_css_selector( $sanitized_selector );

				if ( '' === $validated_selector ) {
					$this->log_security_event( sprintf( 'CSS selector failed validation: %s', $selector ), 'critical' );
					continue;
				}

				$clean_selectors[] = $validated_selector;
			}

			if ( ! empty( $clean_selectors ) ) {
				$clean_selectors = array_values( array_unique( $clean_selectors ) );
				$sanitized['exclude_selectors'] = array_slice( $clean_selectors, 0, 50 );
			} else {
				$sanitized['exclude_selectors'] = array();
			}
		}

		if ( $sanitized !== $current_options ) {
			VazirFontPlugin::clear_cache();
		}

		return $sanitized;
	}

	/**
	 * Sanitize CSS selectors received from settings.
	 *
	 * @param string $selector Raw selector input.
	 * @return string
	 */
	private function sanitize_css_selector( $selector ) {
		$selector = (string) $selector;
		$selector = str_ireplace( array( '@import', 'url(' ), '', $selector );
		$selector = preg_replace( '/\/\*.*?\*\//', '', $selector );
		$selector = str_replace( array( '{', '}', ';' ), ' ', $selector );
		$selector = preg_replace( '/[^a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~]/', '', $selector );
		$selector = trim( preg_replace( '/\s+/', ' ', $selector ) );

		if ( strlen( $selector ) > 200 ) {
			$selector = substr( $selector, 0, 200 );
		}

		return $selector;
	}

	/**
	 * Validate a sanitized CSS selector.
	 *
	 * @param string $selector Sanitized selector.
	 * @return string
	 */
	private function validate_css_selector( $selector ) {
		if ( '' === $selector ) {
			return '';
		}

		if ( false !== strpos( $selector, '{' ) || false !== strpos( $selector, '}' ) || false !== strpos( $selector, ';' ) ) {
			return '';
		}

		if ( preg_match( '/\/\*/', $selector ) ) {
			return '';
		}

		if ( ! preg_match( '/^[a-zA-Z.#]/', $selector ) ) {
			return '';
		}

		if ( ! preg_match( '/^[a-zA-Z0-9\s\-\_\.\:#\*\[\]\(\),>+~]+$/', $selector ) ) {
			return '';
		}

		$invalid_sequences = array( '##', '..', ',,', '>>', '++', '~~', '**' );

		foreach ( $invalid_sequences as $sequence ) {
			if ( false !== strpos( $selector, $sequence ) ) {
				return '';
			}
		}

		return $selector;
	}

	/**
	 * Log security-sensitive events to the debug log when enabled.
	 *
	 * @param string $event    Event description.
	 * @param string $severity Severity level.
	 */
	private function log_security_event( $event, $severity = 'warning' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[Vazir Font Security] [%1$s] User %2$d: %3$s',
					$severity,
					get_current_user_id(),
					$event
				)
			);
		}
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section_desc() {
		echo '<p>' . esc_html__( 'انتخاب کنید فونت در کدام بخش‌ها فعال باشد.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * Render weights section description.
	 */
	public function render_weights_section_desc() {
		echo '<p>' . esc_html__( 'وزن‌های مورد نیاز را انتخاب کنید تا فقط فونت‌های ضروری بارگذاری شوند.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 */
	public function render_advanced_section_desc() {
		echo '<p>' . esc_html__( 'انتخابگرهایی که باید از اعمال فونت مستثنی شوند را تعیین کنید.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options  = VazirFontPlugin::get_options();
		$name     = $args['name'];
		$label    = $args['label'];
		$field_id = 'vazir-font-' . sanitize_key( $name );
		$checked  = ! empty( $options[ $name ] );

		echo '<fieldset>';
		echo '<label for="' . esc_attr( $field_id ) . '">';
		echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="vazir_font_options[' . esc_attr( $name ) . ']" value="1" ' . checked( $checked, true, false ) . ' />';
		echo ' ' . esc_html( $label );
		echo '</label>';
		echo '</fieldset>';
	}

	/**
	 * Render font weights field.
	 */
	public function render_weights_field() {
		$options  = VazirFontPlugin::get_options();
		$selected = isset( $options['font_weights'] ) ? (array) $options['font_weights'] : array( '400' );
		$weights  = array(
			'300' => __( '300 (Light)', 'vazir-font-wp' ),
			'400' => __( '400 (Regular)', 'vazir-font-wp' ),
			'500' => __( '500 (Medium)', 'vazir-font-wp' ),
			'700' => __( '700 (Bold)', 'vazir-font-wp' ),
			'900' => __( '900 (Black)', 'vazir-font-wp' ),
		);

		echo '<fieldset>';
		foreach ( $weights as $weight => $label ) {
			$field_id = 'vazir-font-weight-' . $weight;
			$checked  = in_array( $weight, $selected, true );
			echo '<label for="' . esc_attr( $field_id ) . '" class="vazir-font-weight-option">';
			echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="vazir_font_options[font_weights][]" value="' . esc_attr( $weight ) . '" ' . checked( $checked, true, false ) . ' /> ';
			echo esc_html( $label );
			echo '</label><br />';
		}
		echo '</fieldset>';
	}

	/**
	 * Render textarea field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_textarea_field( $args ) {
		$options     = VazirFontPlugin::get_options();
		$name        = $args['name'];
		$description = $args['description'];
		$field_id    = 'vazir-font-' . sanitize_key( $name );
		$value       = implode( "\n", isset( $options[ $name ] ) ? (array) $options[ $name ] : array() );

		echo '<label class="screen-reader-text" for="' . esc_attr( $field_id ) . '">' . esc_html__( 'استثناء انتخابگرها', 'vazir-font-wp' ) . '</label>';
		echo '<textarea id="' . esc_attr( $field_id ) . '" name="vazir_font_options[' . esc_attr( $name ) . ']" rows="6" cols="50" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $description ) ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Add a settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . $this->page_slug ) ) . '">' . esc_html__( 'تنظیمات', 'vazir-font-wp' ) . '</a>';
		$links[]       = $settings_link;

		return $links;
	}
}
