<?php
/**
 * Plugin Name:       Vazir Font for WordPress
 * Plugin URI:        https://github.com/rezahh107/Vazir
 * Description:       Self-hosted Persian typography for WordPress, editor contexts, Gravity Forms, Gravity Flow, Gravity Perks, and bounded GravityView editor surfaces.
 * Version:           1.5.0
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

const VAZIR_FONT_VERSION          = '1.5.0';
const VAZIR_FONT_PLUGIN_FILE      = __FILE__;
const VAZIR_FONT_PLUGIN_DIR       = __DIR__ . '/';
const VAZIR_FONT_OPTION_NAME      = 'vazir_font_options';
const VAZIR_FONT_DB_VERSION_KEY   = 'vazir_font_db_version';
const VAZIR_FONT_SCHEMA_VERSION   = '1.3.0';
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
		add_action( 'init', [ $this, 'load_textdomain' ] );
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

	/**
	 * Load translations at init or later, as required by current WordPress i18n guidance.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'vazir-font-wp',
			false,
			dirname( plugin_basename( VAZIR_FONT_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	public function init(): void {
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

		if ( class_exists( 'Gravity_Flow' ) && class_exists( 'VazirFont_GravityFlow_Integration' ) ) {
			VazirFont_GravityFlow_Integration::get_instance();
		}

		if ( class_exists( 'GWPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' ) ) {
			VazirFont_GravityPerks_Integration::get_instance();
		}

		if ( class_exists( 'GV\Plugin' ) && class_exists( 'VazirFont_GravityView_Integration' ) ) {
			VazirFont_GravityView_Integration::get_instance();
		}
	}

	public static function activate(): void {
		self::maybe_migrate_options_schema_static();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( VAZIR_FONT_LEGACY_CRON_HOOK );
	}

	private function maybe_migrate_options_schema(): void {
		self::maybe_migrate_options_schema_static();
	}

	private static function maybe_migrate_options_schema_static(): void {
		$stored_schema_version = (string) get_option( VAZIR_FONT_DB_VERSION_KEY, '' );
		if ( VAZIR_FONT_SCHEMA_VERSION === $stored_schema_version ) {
			return;
		}

		$options = self::normalize_options( get_option( VAZIR_FONT_OPTION_NAME, [] ) );
		update_option( VAZIR_FONT_OPTION_NAME, $options );
		update_option( VAZIR_FONT_DB_VERSION_KEY, VAZIR_FONT_SCHEMA_VERSION );
		wp_clear_scheduled_hook( VAZIR_FONT_LEGACY_CRON_HOOK );
		self::clear_cache();
	}

	public static function get_options(): array {
		if ( null === self::$cached_options ) {
			self::$cached_options = self::normalize_options( get_option( VAZIR_FONT_OPTION_NAME, [] ) );
		}
		return self::$cached_options;
	}

	public static function clear_cache(): void {
		self::$cached_options = null;
	}

	private static function normalize_options( $raw ): array {
		$defaults = [
			'enable_frontend'      => true,
			'enable_admin'         => true,
			'enable_gravity_forms' => true,
			'font_weights'         => [ '300', '400', '500', '700', '900' ],
			'exclude_selectors'    => [],
		];

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$options = $defaults;

		foreach ( [ 'enable_frontend', 'enable_admin', 'enable_gravity_forms' ] as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$options[ $key ] = (bool) $raw[ $key ];
			}
		}

		if ( isset( $raw['font_weights'] ) && is_array( $raw['font_weights'] ) ) {
			$allowed = [ '300', '400', '500', '700', '900' ];
			$weights = array_values( array_intersect( $allowed, array_map( 'strval', $raw['font_weights'] ) ) );
			if ( [] !== $weights ) {
				$options['font_weights'] = $weights;
			}
		}

		if ( isset( $raw['exclude_selectors'] ) && is_array( $raw['exclude_selectors'] ) ) {
			$options['exclude_selectors'] = array_values(
				array_filter(
					array_map(
						static fn( $selector ): string => trim( (string) $selector ),
						$raw['exclude_selectors']
					),
					static fn( string $selector ): bool => '' !== $selector
				)
			);
		}

		return $options;
	}
}

VazirFontPlugin::get_instance();
