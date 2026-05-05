<?php
/**
 * Plugin Name:       Vazir Font for WordPress
 * Plugin URI:        https://github.com/rastikerdar/vazir-font-wp
 * Description:       اضافه کردن فونت وزیر به تمام بخش‌های وردپرس شامل فرانت، ادمین و گرویتی فرمز
 * Version:           1.2.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:           Reza Hashemi Hosseini
 * Text Domain:       vazir-font-wp
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin constants.
 */
const VAZIR_FONT_VERSION        = '1.2.0';
const VAZIR_FONT_PLUGIN_FILE    = __FILE__;
const VAZIR_FONT_PLUGIN_DIR     = __DIR__ . '/';
const VAZIR_FONT_PLUGIN_URL     = plugin_dir_url( __FILE__ );
const VAZIR_FONT_ASSETS_URL     = VAZIR_FONT_PLUGIN_URL . 'assets/';
const VAZIR_FONT_FONTS_URL      = VAZIR_FONT_ASSETS_URL . 'fonts/';

const VAZIR_FONT_OPTION_NAME    = 'vazir_font_options';
const VAZIR_FONT_CRON_HOOK      = 'vazir_font_clear_cache';
const VAZIR_FONT_CRON_SCHEDULE  = 'weekly';
const VAZIR_FONT_DB_VERSION_KEY = 'vazir_font_db_version';

/**
 * Autoloader for plugin classes.
 *
 * This assumes class names follow the VazirFont_* pattern and reside under
 * the /includes directory.
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( strncmp( $class, 'VazirFont_', 10 ) !== 0 ) {
			return;
		}

		$file = VAZIR_FONT_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Main plugin bootstrap/controller.
 */
final class VazirFontPlugin {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Cached options for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cached_options = null;

	/**
	 * VazirFontPlugin constructor.
	 *
	 * Private to enforce singleton usage.
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
		throw new RuntimeException( 'Cannot unserialize VazirFontPlugin singleton.' );
	}

	/**
	 * Get the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register core hooks.
	 */
	private function init_hooks(): void {
		register_activation_hook( VAZIR_FONT_PLUGIN_FILE, [ self::class, 'activate' ] );
		register_deactivation_hook( VAZIR_FONT_PLUGIN_FILE, [ self::class, 'deactivate' ] );

		add_action( 'plugins_loaded', [ $this, 'init' ] );
		add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );
	}

	/**
	 * Plugin runtime initialization.
	 */
	public function init(): void {
		// Load translations.
		load_plugin_textdomain(
			'vazir-font-wp',
			false,
			dirname( plugin_basename( VAZIR_FONT_PLUGIN_FILE ) ) . '/languages/'
		);

		// One-time DB migration for options schema.
		$this->maybe_migrate_options_schema();

		// Initialize loader.
		if ( class_exists( 'VazirFont_Loader' ) ) {
			VazirFont_Loader::get_instance();
		}

		// Admin settings.
		if ( is_admin() && class_exists( 'VazirFont_Admin_Settings' ) ) {
			VazirFont_Admin_Settings::get_instance();
		}

		// Gravity Forms integration.
		if ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) ) {
			VazirFont_GravityForms_Integration::get_instance();
		}
	}

	/**
	 * Plugin activation callback.
	 */
	public static function activate(): void {
		$defaults = self::get_default_options();

		$current = get_option( VAZIR_FONT_OPTION_NAME, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		// Merge only valid keys.
		$options = [];
		foreach ( $defaults as $key => $default_value ) {
			$options[ $key ] = array_key_exists( $key, $current ) ? $current[ $key ] : $default_value;
		}
		update_option( VAZIR_FONT_OPTION_NAME, $options );

		// Schedule weekly cache clearing, but not immediately.
		if ( ! wp_next_scheduled( VAZIR_FONT_CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, VAZIR_FONT_CRON_SCHEDULE, VAZIR_FONT_CRON_HOOK );
		}
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( VAZIR_FONT_CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, VAZIR_FONT_CRON_HOOK );
		}
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @param array<string, array<string, mixed>> $schedules
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_schedules( array $schedules ): array {
		if ( ! isset( $schedules[ VAZIR_FONT_CRON_SCHEDULE ] ) ) {
			$schedules[ VAZIR_FONT_CRON_SCHEDULE ] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly (Vazir Font)', 'vazir-font-wp' ),
			];
		}

		return $schedules;
	}

	/**
	 * Get plugin options with in-request caching.
	 * Only keys present in the default schema are returned (legacy keys are dropped).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options(): array {
		if ( null !== self::$cached_options ) {
			return self::$cached_options;
		}

		$defaults = self::get_default_options();
		$options  = get_option( VAZIR_FONT_OPTION_NAME, [] );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		// Filter to only allowed keys.
		$normalized = [];
		foreach ( $defaults as $key => $default_value ) {
			$normalized[ $key ] = array_key_exists( $key, $options ) ? $options[ $key ] : $default_value;
		}

		// Normalize specific fields.
		$normalized['font_weights']      = self::normalize_font_weights( $normalized['font_weights'] );
		$normalized['exclude_selectors'] = self::normalize_exclude_selectors( $normalized['exclude_selectors'] );

		self::$cached_options = $normalized;

		return $normalized;
	}

	/**
	 * Update plugin options and clear cache.
	 * Only keys defined in the default schema are accepted.
	 *
	 * @param array<string, mixed> $new_options
	 */
	public static function update_options( array $new_options ): void {
		$defaults = self::get_default_options();

		// Merge only valid keys.
		$merged = [];
		foreach ( $defaults as $key => $default_value ) {
			if ( array_key_exists( $key, $new_options ) ) {
				$merged[ $key ] = $new_options[ $key ];
			} else {
				$merged[ $key ] = self::get_options()[ $key ] ?? $default_value;
			}
		}

		update_option( VAZIR_FONT_OPTION_NAME, $merged );
		self::clear_cache();
	}

	/**
	 * Fire cache clearing hook and reset in-request cache.
	 */
	public static function clear_cache(): void {
		self::$cached_options = null;
		do_action( VAZIR_FONT_CRON_HOOK );
	}

	/**
	 * Get default plugin options.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_options(): array {
		return [
			'enable_frontend'      => true,
			'enable_admin'         => true,
			'enable_gravity_forms' => true,
			'font_weights'         => [ '300', '400', '500', '700', '900' ],
			'exclude_selectors'    => [
				'.dashicons',
				'.menu-icon',
				'.menu-image',
				'[class^="dashicons-"]',
				'[class*=" dashicons-"]',
				'[class^="fa-"]',
				'[class*=" fa-"]',
				'.material-icons',
				'[data-icon]:before',
			],
		];
	}

	/**
	 * Normalize font weights into a unique list of strings.
	 *
	 * @param mixed $weights
	 *
	 * @return string[]
	 */
	private static function normalize_font_weights( $weights ): array {
		if ( ! is_array( $weights ) ) {
			$weights = [];
		}
		$weights = array_map( 'strval', $weights );
		$weights = array_unique( $weights );

		return array_values( $weights );
	}

	/**
	 * Normalize exclude selectors into a clean list of strings.
	 *
	 * @param mixed $selectors
	 *
	 * @return string[]
	 */
	private static function normalize_exclude_selectors( $selectors ): array {
		if ( ! is_array( $selectors ) ) {
			$selectors = [];
		}
		$selectors = array_map( static function ( $selector ): string {
			return trim( (string) $selector );
		}, $selectors );
		$selectors = array_filter( $selectors, static fn( string $selector ): bool => $selector !== '' );

		return array_values( $selectors );
	}

	/**
	 * One‑time migration that cleans up stored options by removing
	 * any keys not present in the default schema.
	 */
	private static function migrate_options_schema(): void {
		$defaults = self::get_default_options();
		$options  = get_option( VAZIR_FONT_OPTION_NAME, [] );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$clean = [];
		foreach ( $defaults as $key => $default_value ) {
			$clean[ $key ] = array_key_exists( $key, $options ) ? $options[ $key ] : $default_value;
		}

		update_option( VAZIR_FONT_OPTION_NAME, $clean );
	}

	/**
	 * Run schema migration once per plugin version upgrade.
	 */
	private function maybe_migrate_options_schema(): void {
		$current_db_version = get_option( VAZIR_FONT_DB_VERSION_KEY, '1.0.0' );

		if ( version_compare( $current_db_version, '1.2.0', '<' ) ) {
			self::migrate_options_schema();
			update_option( VAZIR_FONT_DB_VERSION_KEY, '1.2.0' );
		}
	}
}

// Bootstrap the plugin.
VazirFontPlugin::get_instance();
