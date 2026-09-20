<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the Vazir font admin settings page.
 */
final class VazirFont_Admin_Settings {
	private const PAGE_SLUG = 'vazir-font-settings';
	private const ALLOWED_WEIGHTS = [ '300', '400', '500', '700', '900' ];
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

	private static ?self $instance = null;

	private function __construct() {
		$this->init_hooks();
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFont_Admin_Settings singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function init_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'init_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( VAZIR_FONT_PLUGIN_FILE ), [ $this, 'add_settings_link' ] );
	}

	/**
	 * Enqueue admin assets only on the plugin settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'vazir-font-admin', VAZIR_FONT_ASSETS_URL . 'css/admin.css', [], VAZIR_FONT_VERSION );
		wp_enqueue_script( 'vazir-font-admin', VAZIR_FONT_ASSETS_URL . 'js/admin.js', [ 'jquery' ], VAZIR_FONT_VERSION, true );
	}

	public function add_admin_menu(): void {
		add_options_page(
			__( 'تنظیمات فونت وزیر', 'vazir-font-wp' ),
			__( 'فونت وزیر', 'vazir-font-wp' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	public function init_settings(): void {
		register_setting(
			'vazir_font_settings',
			VAZIR_FONT_OPTION_NAME,
			[ $this, 'sanitize_options' ]
		);

		add_settings_section(
			'vazir_font_general',
			__( 'محل اعمال فونت', 'vazir-font-wp' ),
			[ $this, 'render_general_section_desc' ],
			self::PAGE_SLUG
		);

		$this->add_checkbox_setting(
			'enable_frontend',
			__( 'سایت (فرانت‌اند)', 'vazir-font-wp' ),
			__( 'متن و کنترل‌های بخش عمومی سایت با Vazirmatn نمایش داده شوند.', 'vazir-font-wp' )
		);
		$this->add_checkbox_setting(
			'enable_admin',
			__( 'مدیریت و ویرایشگر وردپرس', 'vazir-font-wp' ),
			__( 'رابط مدیریت، صفحه ورود و محتوای ویرایشگرهای وردپرس با Vazirmatn نمایش داده شوند.', 'vazir-font-wp' )
		);
		$this->add_checkbox_setting(
			'enable_gravity_forms',
			__( 'Gravity Forms', 'vazir-font-wp' ),
			__( 'در صورت در دسترس بودن Gravity Forms، تایپوگرافی فرم‌ها نیز با تنظیمات همین افزونه هماهنگ شود.', 'vazir-font-wp' )
		);

		add_settings_section(
			'vazir_font_weights',
			__( 'وزن‌های Vazirmatn', 'vazir-font-wp' ),
			[ $this, 'render_weights_section_desc' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'font_weights',
			__( 'وزن‌های قابل بارگذاری', 'vazir-font-wp' ),
			[ $this, 'render_weights_field' ],
			self::PAGE_SLUG,
			'vazir_font_weights',
			[
				'class' => 'vazir-font-setting-row vazir-font-setting-row--weights',
			]
		);

		add_settings_section(
			'vazir_font_advanced',
			__( 'تنظیمات پیشرفته', 'vazir-font-wp' ),
			[ $this, 'render_advanced_section_desc' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			'exclude_selectors',
			__( 'استثناءهای CSS', 'vazir-font-wp' ),
			[ $this, 'render_textarea_field' ],
			self::PAGE_SLUG,
			'vazir_font_advanced',
			[
				'name'      => 'exclude_selectors',
				'label_for' => 'vazir-font-exclude_selectors',
				'class'     => 'vazir-font-setting-row vazir-font-setting-row--advanced',
			]
		);
	}

	private function add_checkbox_setting( string $name, string $title, string $description ): void {
		$id = 'vazir-font-' . sanitize_key( $name );
		add_settings_field(
			$name,
			$title,
			[ $this, 'render_checkbox_field' ],
			self::PAGE_SLUG,
			'vazir_font_general',
			[
				'name'        => $name,
				'description' => $description,
				'label_for'   => $id,
				'class'       => 'vazir-font-setting-row',
			]
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'vazir-font-wp' ) );
		}
		?>
		<div class="wrap vazir-font-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="vazir-font-settings__orientation">
				<p><?php esc_html_e( 'این صفحه تنظیمات افزونه «فونت وزیر» است. قلم همراه افزونه Vazirmatn نسخه 33.003 است.', 'vazir-font-wp' ); ?></p>
				<p class="description"><?php esc_html_e( 'گزینه‌های علامت‌خورده نشان می‌دهند فونت در کدام بخش‌ها فعال است. برای بیشتر سایت‌ها همین تنظیمات اصلی کافی است.', 'vazir-font-wp' ); ?></p>
			</div>

			<?php settings_errors(); ?>

			<form id="vazir-font-settings-form" method="post" action="options.php">
				<?php settings_fields( 'vazir_font_settings' ); ?>

				<section class="vazir-font-settings__section" aria-labelledby="vazir-font-general-heading">
					<h2 id="vazir-font-general-heading"><?php esc_html_e( 'محل اعمال فونت', 'vazir-font-wp' ); ?></h2>
					<?php $this->render_general_section_desc(); ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( self::PAGE_SLUG, 'vazir_font_general' ); ?>
					</table>
				</section>

				<section class="vazir-font-settings__section" aria-labelledby="vazir-font-weights-heading">
					<h2 id="vazir-font-weights-heading"><?php esc_html_e( 'وزن‌های Vazirmatn', 'vazir-font-wp' ); ?></h2>
					<?php $this->render_weights_section_desc(); ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( self::PAGE_SLUG, 'vazir_font_weights' ); ?>
					</table>
					<?php $this->render_font_preview(); ?>
				</section>

				<details class="vazir-font-settings__advanced" id="vazir-font-advanced">
					<summary><?php esc_html_e( 'تنظیمات پیشرفته: استثناءها', 'vazir-font-wp' ); ?></summary>
					<div class="vazir-font-settings__advanced-content">
						<?php $this->render_advanced_section_desc(); ?>
						<table class="form-table" role="presentation">
							<?php do_settings_fields( self::PAGE_SLUG, 'vazir_font_advanced' ); ?>
						</table>
					</div>
				</details>

				<?php submit_button( __( 'ذخیره تنظیمات', 'vazir-font-wp' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function render_font_preview(): void {
		$options  = VazirFontPlugin::get_options();
		$selected = $options['font_weights'] ?? [ '400' ];
		if ( ! is_array( $selected ) ) {
			$selected = [ '400' ];
		}
		$weights = $this->get_weight_metadata();
		?>
		<div class="vazir-font-preview" aria-labelledby="vazir-font-preview-heading">
			<h3 id="vazir-font-preview-heading"><?php esc_html_e( 'پیش‌نمایش Vazirmatn', 'vazir-font-wp' ); ?></h3>
			<p class="description"><?php esc_html_e( 'نمونه‌های زیر فقط وزن‌هایی را نشان می‌دهند که در فرم انتخاب شده‌اند.', 'vazir-font-wp' ); ?></p>
			<div class="vazir-font-preview__samples">
				<?php foreach ( $weights as $weight_metadata ) : ?>
					<?php
					$weight = $weight_metadata['weight'];
					$label  = $weight_metadata['label'];
					?>
					<p class="vazir-font-preview__sample" data-weight="<?php echo esc_attr( $weight ); ?>"<?php echo in_array( $weight, $selected, true ) ? '' : ' hidden'; ?> style="font-weight: <?php echo esc_attr( $weight ); ?>;">
						<span dir="ltr"><?php echo esc_html( $weight ); ?></span>
						<span aria-hidden="true"> — </span>
						<?php echo esc_html( $label ); ?>
						<span class="vazir-font-preview__phrase"><?php esc_html_e( 'متن فارسی برای پیش‌نمایش خوانایی', 'vazir-font-wp' ); ?></span>
					</p>
				<?php endforeach; ?>
				<p id="vazir-font-preview-empty" class="description" role="status" aria-live="polite" hidden><?php esc_html_e( 'وزنی انتخاب نشده است. هنگام ذخیره، وزن 400 به‌صورت خودکار فعال می‌شود.', 'vazir-font-wp' ); ?></p>
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
			wp_die( esc_html__( 'شما اجازه تغییر این تنظیمات را ندارید.', 'vazir-font-wp' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'vazir_font_settings-options' ) ) {
			$this->log_security_event( 'Nonce verification failed during settings save.', 'warning' );
			add_settings_error(
				VAZIR_FONT_OPTION_NAME,
				'nonce_failed',
				esc_html__( 'اعتبارسنجی امنیتی انجام نشد. صفحه را تازه کنید و دوباره ذخیره کنید.', 'vazir-font-wp' ),
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
				VAZIR_FONT_OPTION_NAME,
				'rate_limit',
				esc_html__( 'تعداد تلاش‌های ذخیره زیاد است. یک دقیقه صبر کنید و دوباره تلاش کنید.', 'vazir-font-wp' ),
				'error'
			);
			return VazirFontPlugin::get_options();
		}
		set_transient( $transient_key, $save_count + 1, MINUTE_IN_SECONDS );

		$current_options = VazirFontPlugin::get_options();
		$sanitized       = [];
		$input           = is_array( $input ) ? $input : [];

		$checkboxes = [ 'enable_frontend', 'enable_admin', 'enable_gravity_forms' ];
		foreach ( $checkboxes as $checkbox ) {
			$value = false;
			if ( isset( $input[ $checkbox ] ) ) {
				$filtered = filter_var( $input[ $checkbox ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				$value    = ( null === $filtered ) ? false : (bool) $filtered;
			}
			$sanitized[ $checkbox ] = $value;
		}

		$selected = isset( $input['font_weights'] ) && is_array( $input['font_weights'] )
			? array_map( 'sanitize_text_field', $input['font_weights'] )
			: [];
		$valid = array_values( array_intersect( $selected, self::ALLOWED_WEIGHTS ) );
		if ( empty( $valid ) ) {
			$valid = [ '400' ];
			add_settings_error(
				VAZIR_FONT_OPTION_NAME,
				'no_weights_selected',
				esc_html__( 'هیچ وزن معتبری انتخاب نشده بود؛ وزن 400 به‌صورت خودکار فعال شد.', 'vazir-font-wp' ),
				'warning'
			);
			$this->log_security_event( 'No valid font weights selected; defaulted to 400.', 'notice' );
		}
		$sanitized['font_weights'] = $valid;

		$sanitized['exclude_selectors'] = $current_options['exclude_selectors'] ?? self::DEFAULT_EXCLUDE_SELECTORS;
		if ( isset( $input['exclude_selectors'] ) && is_string( $input['exclude_selectors'] ) ) {
			$raw_lines = explode( "\n", $input['exclude_selectors'] );
			$raw_lines = array_map( 'trim', $raw_lines );
			$raw_lines = array_filter( $raw_lines, static fn( string $selector ): bool => '' !== $selector );

			$clean    = [];
			$rejected = 0;
			foreach ( $raw_lines as $selector ) {
				$sanitized_selector = $this->sanitize_css_selector( $selector );
				$validated          = $this->validate_css_selector( $sanitized_selector );
				if ( '' === $validated ) {
					++$rejected;
					$this->log_security_event( sprintf( 'CSS selector rejected during validation: %s', $selector ), 'notice' );
					continue;
				}
				$clean[] = $validated;
			}

			$clean = array_values( array_unique( $clean ) );
			if ( $rejected > 0 ) {
				add_settings_error(
					VAZIR_FONT_OPTION_NAME,
					'invalid_selectors',
					sprintf(
						/* translators: %d: number of rejected CSS selector lines. */
						esc_html__( '%d خط از استثناءها معتبر نبود و ذخیره نشد. هر خط باید یک CSS selector ساده و مستقل باشد.', 'vazir-font-wp' ),
						$rejected
					),
					'warning'
				);
			}
			if ( count( $clean ) > 50 ) {
				add_settings_error(
					VAZIR_FONT_OPTION_NAME,
					'selector_limit',
					esc_html__( 'حداکثر 50 استثناء ذخیره می‌شود؛ موارد بعدی ذخیره نشدند.', 'vazir-font-wp' ),
					'warning'
				);
			}
			$sanitized['exclude_selectors'] = array_slice( $clean, 0, 50 );
		}

		if ( $sanitized !== $current_options ) {
			VazirFontPlugin::clear_cache();
		}

		return $sanitized;
	}

	private function sanitize_css_selector( string $selector ): string {
		$selector = sanitize_text_field( $selector );
		$selector = trim( (string) preg_replace( '/\s+/', ' ', $selector ) );

		if ( strlen( $selector ) > 200 ) {
			return '';
		}

		return $selector;
	}

	private function validate_css_selector( string $selector ): string {
		if ( '' === $selector ) {
			return '';
		}
		if ( false !== stripos( $selector, '@import' ) || false !== stripos( $selector, 'url(' ) ) {
			return '';
		}
		if ( false !== strpos( $selector, '{' ) || false !== strpos( $selector, '}' ) || false !== strpos( $selector, ';' ) ) {
			return '';
		}
		if ( false !== strpos( $selector, '/*' ) || false !== strpos( $selector, '*/' ) ) {
			return '';
		}
		if ( ! preg_match( '/^[a-zA-Z.#\[]/', $selector ) ) {
			return '';
		}
		if ( ! preg_match( '/^[a-zA-Z0-9\s\-_\.#:\*\[\]\(\),>+~="\'\^\$\|]+$/', $selector ) ) {
			return '';
		}
		if ( substr_count( $selector, '[' ) !== substr_count( $selector, ']' ) ) {
			return '';
		}
		if ( substr_count( $selector, '(' ) !== substr_count( $selector, ')' ) ) {
			return '';
		}
		if ( substr_count( $selector, '"' ) % 2 !== 0 || substr_count( $selector, "'" ) % 2 !== 0 ) {
			return '';
		}

		$invalid = [ '##', '..', ',,', '>>', '++', '~~', '**' ];
		foreach ( $invalid as $sequence ) {
			if ( false !== strpos( $selector, $sequence ) ) {
				return '';
			}
		}

		return $selector;
	}

	private function log_security_event( string $event, string $severity = 'warning' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[Vazir Font Security] [%s] User %d: %s',
					$severity,
					get_current_user_id(),
					$event
				)
			);
		}
	}

	public function render_general_section_desc(): void {
		echo '<p class="description">' . esc_html__( 'هر گزینه فقط همان محیط را کنترل می‌کند. با برداشتن علامت، فونت در آن محیط اعمال نمی‌شود.', 'vazir-font-wp' ) . '</p>';
	}

	public function render_weights_section_desc(): void {
		echo '<p id="vazir-font-weights-help" class="description">' . esc_html__( 'فقط وزن‌هایی را انتخاب کنید که واقعاً نیاز دارید. وزن‌های کمتر می‌توانند دانلود فونت را کاهش دهند. اگر هیچ وزن معتبری انتخاب نشود، وزن 400 هنگام ذخیره برگردانده می‌شود.', 'vazir-font-wp' ) . '</p>';
	}

	public function render_advanced_section_desc(): void {
		echo '<p>' . esc_html__( 'اگر بخشی از سایت باید فونت خودش را نگه دارد، می‌توانید آن را از اعمال Vazirmatn مستثنی کنید. این بخش برای کاربران آشنا با CSS است و در استفاده عادی لازم نیست تغییر کند.', 'vazir-font-wp' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'هر خط یک CSS selector مستقل است. خطوط نامعتبر نادیده گرفته می‌شوند و پس از ذخیره هشدار نمایش داده می‌شود.', 'vazir-font-wp' ) . '</p>';
		echo '<p class="description vazir-font-settings__examples">' . esc_html__( 'نمونه:', 'vazir-font-wp' ) . ' <code dir="ltr">.no-vazirmatn</code> ' . esc_html__( 'یا', 'vazir-font-wp' ) . ' <code dir="ltr">#legacy-widget</code></p>';
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_checkbox_field( array $args ): void {
		$options     = VazirFontPlugin::get_options();
		$name        = $args['name'];
		$description = $args['description'] ?? '';
		$id          = 'vazir-font-' . sanitize_key( $name );
		$checked     = ! empty( $options[ $name ] );
		$desc_id     = $id . '-description';

		echo '<div class="vazir-font-setting-control">';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( VAZIR_FONT_OPTION_NAME ) . '[' . esc_attr( $name ) . ']" value="1" aria-describedby="' . esc_attr( $desc_id ) . '" ' . checked( $checked, true, false ) . ' />';
		echo '<span>' . esc_html__( 'فعال باشد', 'vazir-font-wp' ) . '</span>';
		echo '</div>';
		if ( '' !== $description ) {
			echo '<p class="description" id="' . esc_attr( $desc_id ) . '">' . esc_html( $description ) . '</p>';
		}

		if ( 'enable_gravity_forms' === $name && ! class_exists( 'GFForms' ) ) {
			echo '<p class="description vazir-font-setting-availability">' . esc_html__( 'Gravity Forms اکنون فعال نیست. این ترجیح ذخیره می‌شود و در صورت فعال‌شدن Gravity Forms اعمال خواهد شد.', 'vazir-font-wp' ) . '</p>';
		}
	}

	public function render_weights_field(): void {
		$options  = VazirFontPlugin::get_options();
		$selected = $options['font_weights'] ?? [ '400' ];
		if ( ! is_array( $selected ) ) {
			$selected = [ '400' ];
		}

		echo '<fieldset class="vazir-font-weight-options" aria-describedby="vazir-font-weights-help">';
		echo '<legend class="screen-reader-text">' . esc_html__( 'وزن‌های قابل بارگذاری Vazirmatn', 'vazir-font-wp' ) . '</legend>';
		foreach ( $this->get_weight_metadata() as $weight_metadata ) {
			$weight  = $weight_metadata['weight'];
			$label   = $weight_metadata['label'];
			$id      = 'vazir-font-weight-' . $weight;
			$checked = in_array( $weight, $selected, true );
			echo '<label for="' . esc_attr( $id ) . '" class="vazir-font-weight-option">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( VAZIR_FONT_OPTION_NAME ) . '[font_weights][]" value="' . esc_attr( $weight ) . '" ' . checked( $checked, true, false ) . ' /> ';
			echo '<span dir="ltr">' . esc_html( $weight ) . '</span> — ' . esc_html( $label );
			echo '</label>';
		}
		echo '</fieldset>';
	}

	/**
	 * @return array<int, array{weight: string, label: string}>
	 */
	private function get_weight_metadata(): array {
		return [
			[
				'weight' => '300',
				'label'  => __( 'نازک', 'vazir-font-wp' ),
			],
			[
				'weight' => '400',
				'label'  => __( 'معمولی', 'vazir-font-wp' ),
			],
			[
				'weight' => '500',
				'label'  => __( 'متوسط', 'vazir-font-wp' ),
			],
			[
				'weight' => '700',
				'label'  => __( 'ضخیم', 'vazir-font-wp' ),
			],
			[
				'weight' => '900',
				'label'  => __( 'بسیار ضخیم', 'vazir-font-wp' ),
			],
		];
	}

	/**
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_textarea_field( array $args ): void {
		$options = VazirFontPlugin::get_options();
		$name    = $args['name'];
		$id      = 'vazir-font-' . sanitize_key( $name );
		$value   = isset( $options[ $name ] ) && is_array( $options[ $name ] ) ? implode( "\n", $options[ $name ] ) : '';

		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( VAZIR_FONT_OPTION_NAME ) . '[' . esc_attr( $name ) . ']" rows="8" class="large-text code" dir="ltr" spellcheck="false" aria-describedby="vazir-font-exclude_selectors-help">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description" id="vazir-font-exclude_selectors-help">' . esc_html__( 'هر selector را در یک خط بنویسید. حداکثر 50 مورد ذخیره می‌شود.', 'vazir-font-wp' ) . '</p>';
	}

	/**
	 * @param array<string> $links Existing links.
	 * @return array<string>
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'تنظیمات', 'vazir-font-wp' ) . '</a>';
		$links[]       = $settings_link;

		return $links;
	}
}
