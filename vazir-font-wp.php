<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Vazir Font for WordPress
 * Plugin URI: https://github.com/your-username/vazir-font-wp
 * Description: اضافه کردن فونت وزیر به تمام بخش‌های وردپرس شامل فرانت، ادمین و گرویتی فرمز
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vazir-font-wp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Network: false
 *
 * @package Vazir_Font_WP
 */

defined( 'ABSPATH' ) || exit;

define( 'VAZIR_FONT_VERSION', '1.1.0' );
define( 'VAZIR_FONT_PLUGIN_FILE', __FILE__ );
define( 'VAZIR_FONT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VAZIR_FONT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VAZIR_FONT_ASSETS_URL', VAZIR_FONT_PLUGIN_URL . 'assets/' );
define( 'VAZIR_FONT_FONTS_URL', VAZIR_FONT_ASSETS_URL . 'fonts/' );

spl_autoload_register(
	function ( $class ) {
		$prefix   = 'VazirFont_';
		$base_dir = VAZIR_FONT_PLUGIN_DIR . 'includes/';

		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		if ( 'VazirFont_Loader' === $class ) {
			$file = $base_dir . 'class-vazirfont-loader.php';
		}

		if ( 'VazirFont_Admin_Settings' === $class ) {
			$file = $base_dir . 'class-vazirfont-admin-settings.php';
		}

		if ( 'VazirFont_GravityForms_Integration' === $class ) {
			$file = $base_dir . 'class-vazirfont-gravityforms-integration.php';
		}

		if ( file_exists( $file ) ) {
			include $file;
		}
	}
);

/**
 * Main plugin controller.
 */
final class VazirFontPlugin {

	/**
	 * Singleton instance.
	 *
	 * @var VazirFontPlugin|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return VazirFontPlugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * VazirFontPlugin constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register core hooks.
	 */
	private function init_hooks() {
		register_activation_hook( VAZIR_FONT_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( VAZIR_FONT_PLUGIN_FILE, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );
	}

	/**
	 * Initialise localisation and subsystems.
	 */
	public function init() {
		load_plugin_textdomain( 'vazir-font-wp', false, dirname( plugin_basename( VAZIR_FONT_PLUGIN_FILE ) ) . '/languages' );

		VazirFont_Loader::get_instance();

		if ( is_admin() ) {
			VazirFont_Admin_Settings::get_instance();
		}

		if ( class_exists( 'GFForms' ) ) {
			VazirFont_GravityForms_Integration::get_instance();
		}
	}

	/**
	 * Plugin activation callback.
	 */
	public function activate() {
		$defaults = array(
			'enable_frontend'      => true,
			'enable_admin'         => true,
			'enable_gravity_forms' => true,
			'font_weights'         => array( '300', '400', '500', '700', '900' ),
			'exclude_selectors'    => array(
				'.dashicons',
				'.dashicons-before:before',
				'[class*="dashicons"]:before',
				'.wp-menu-image',
				'i.fa',
				'[class*="icon-"]:before',
				'.material-icons',
				'[data-icon]:before',
			),
		);

		add_option( 'vazir_font_options', $defaults );

		if ( ! wp_next_scheduled( 'vazir_font_clear_cache' ) ) {
			wp_schedule_event( time(), 'weekly', 'vazir_font_clear_cache' );
		}
	}

	/**
	 * Plugin deactivation callback.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'vazir_font_clear_cache' );
	}

	/**
	 * Ensure weekly cron schedule exists.
	 *
	 * @param array $schedules Registered schedules.
	 * @return array
	 */
	public function register_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'vazir-font-wp' ),
			);
		}

		return $schedules;
	}

	/**
	 * Retrieve cached plugin options.
	 *
	 * @return array
	 */
	public static function get_options() {
		static $options = null;

		if ( null === $options ) {
			$options = get_option( 'vazir_font_options', array() );

			if ( ! isset( $options['font_weights'] ) ) {
				$options['font_weights'] = array( '400' );
			}

			if ( ! isset( $options['exclude_selectors'] ) ) {
				$options['exclude_selectors'] = array();
			}
		}

		return $options;
	}

	/**
	 * Persist plugin options and clear caches.
	 *
	 * @param array $options Options to store.
	 * @return array
	 */
	public static function update_options( $options ) {
		$old_options = self::get_options();
		$new_options = wp_parse_args( $options, $old_options );

		update_option( 'vazir_font_options', $new_options );
		self::clear_cache();

		return $new_options;
	}

	/**
	 * Trigger cache clearing hook.
	 */
	public static function clear_cache() {
		/**
		 * Allow integrations to clear plugin-specific caches.
		 */
		do_action( 'vazir_font_clear_cache' );
	}
}

VazirFontPlugin::get_instance();
