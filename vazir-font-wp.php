<?php
/**
 * Plugin Name:       Vazir Font for WordPress
 * Plugin URI:        https://github.com/rezahh107/Vazir
 * Description:       Self-hosted Persian typography for WordPress, editor contexts, and Gravity Forms.
 * Version:           1.3.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Reza Hashemi Hosseini
 * Text Domain:       vazir-font-wp
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VAZIR_FONT_VERSION          = '1.3.0';
const VAZIR_FONT_PLUGIN_FILE      = __FILE__;
const VAZIR_FONT_PLUGIN_DIR       = __DIR__ . '/';
const VAZIR_FONT_OPTION_NAME      = 'vazir_font_options';
const VAZIR_FONT_DB_VERSION_KEY   = 'vazir_font_db_version';
const VAZIR_FONT_LEGACY_CRON_HOOK = 'vazir_font_clear_cache';

define( 'VAZIR_FONT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VAZIR_FONT_ASSETS_URL', VAZIR_FONT_PLUGIN_URL . 'assets/' );
define( 'VAZIR_FONT_FONTS_URL', VAZIR_FONT_ASSETS_URL . 'fonts/' );

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

final class VazirFontPlugin {
	private static ?self $instance = null;
	private static ?array $cached_options = null;

	private function __construct() {
		register_activation_hook( VAZIR_FONT_PLUGIN_FILE, [ self::class, 'activate' ] );
		register_deactivation_hook( VAZIR_FONT_PLUGIN_FILE, [ self::class, 'deactivate' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new RuntimeException( 'Cannot unserialize VazirFontPlugin singleton.' );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		load_plugin_textdomain(
			'vazir-font-wp',
			false,
			dirname( plugin_basename( VAZIR_FONT_PLUGIN_FILE ) ) . '/languages/'
		);

		$this->maybe_migrate_options_schema();

		if ( class_exists( 'VazirFont_Loader' ) ) {
			VazirFont_Loader::get_instance();
		}

		if ( is_admin() && class_exists( 'VazirFont_Admin_Settings' ) ) {
			VazirFont_Admin_Settings::get_instance();
		}

		if ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) ) {
			VazirFont_GravityForms_Integration::get_instance();
		}
	}

	public static function activate(): void {
		$defaults = self::get_default_options();
		$current  = get_option( VAZIR_FONT_OPTION_NAME, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$options = [];
		foreach ( $defaults as $key => $default_value ) {
			$options[ $key ] = array_key_exists( $key, $current ) ? $current[ $key ] : $default_value;
		}
		update_option( VAZIR_FONT_OPTION_NAME, $options );
		update_option( VAZIR_FONT_DB_VERSION_KEY, VAZIR_FONT_VERSION );
		self::clear_legacy_cron();
	}

	public static function deactivate(): void {
		self::clear_legacy_cron();
	}

	private static function clear_legacy_cron(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( VAZIR_FONT_LEGACY_CRON_HOOK );
		}
	}

	public static function get_options(): array {
		if ( null !== self::$cached_options ) {
			return self::$cached_options;
		}

		$defaults = self::get_default_options();
		$options  = get_option( VAZIR_FONT_OPTION_NAME, [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$normalized = [];
		foreach ( $defaults as $key => $default_value ) {
			$normalized[ $key ] = array_key_exists( $key, $options ) ? $options[ $key ] : $default_value;
		}
		$normalized['font_weights']      = self::normalize_font_weights( $normalized['font_weights'] );
		$normalized['exclude_selectors'] = self::normalize_exclude_selectors( $normalized['exclude_selectors'] );

		self::$cached_options = $normalized;
		return $normalized;
	}

	public static function update_options( array $new_options ): void {
		$defaults = self::get_default_options();
		$current  = self::get_options();
		$merged   = [];

		foreach ( $defaults as $key => $default_value ) {
			$merged[ $key ] = array_key_exists( $key, $new_options )
				? $new_options[ $key ]
				: ( $current[ $key ] ?? $default_value );
		}

		update_option( VAZIR_FONT_OPTION_NAME, $merged );
		self::clear_cache();
	}

	/**
	 * Clear only this plugin's in-request option cache.
	 *
	 * Font CSS is generated per request from immutable bundled assets, so no
	 * Gravity Forms cache, transient, generated file, or scheduled cleanup is
	 * owned by this plugin.
	 */
	public static function clear_cache(): void {
		self::$cached_options = null;
	}

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

	private static function normalize_font_weights( $weights ): array {
		if ( ! is_array( $weights ) ) {
			$weights = [];
		}
		$allowed = [ '300', '400', '500', '700', '900' ];
		$weights = array_map( 'strval', $weights );
		$weights = array_values( array_unique( array_intersect( $weights, $allowed ) ) );
		if ( [] === $weights ) {
			return [ '400' ];
		}
		if ( ! in_array( '400', $weights, true ) ) {
			array_unshift( $weights, '400' );
		}
		return array_values( array_unique( $weights ) );
	}

	private static function normalize_exclude_selectors( $selectors ): array {
		if ( ! is_array( $selectors ) ) {
			$selectors = [];
		}
		$selectors = array_map(
			static function ( $selector ): string {
				return trim( (string) $selector );
			},
			$selectors
		);
		$selectors = array_filter( $selectors, static fn( string $selector ): bool => '' !== $selector );
		return array_values( $selectors );
	}

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

	private function maybe_migrate_options_schema(): void {
		$current_db_version = (string) get_option( VAZIR_FONT_DB_VERSION_KEY, '1.0.0' );
		if ( version_compare( $current_db_version, VAZIR_FONT_VERSION, '<' ) ) {
			self::migrate_options_schema();
			self::clear_legacy_cron();
			update_option( VAZIR_FONT_DB_VERSION_KEY, VAZIR_FONT_VERSION );
		}
	}
}

VazirFontPlugin::get_instance();
