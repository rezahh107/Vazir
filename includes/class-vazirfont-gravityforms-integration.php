<?php
/**
 * Gravity Forms integration for Vazir font plugin.
 *
 * @package Vazir_Font_WP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides Gravity Forms integration for Vazir font.
 *
 * @package Vazir_Font_WP
 */
class VazirFont_GravityForms_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var VazirFont_GravityForms_Integration|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return VazirFont_GravityForms_Integration
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
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_gravityforms_assets' ), 10, 2 );
	}

	/**
	 * Enqueue fonts for Gravity Forms when enabled.
	 */
	public function enqueue_gravityforms_assets() {
		$options = VazirFontPlugin::get_options();

		if ( empty( $options['enable_gravity_forms'] ) ) {
			return;
		}

		$loader = VazirFont_Loader::get_instance();
		$loader->enqueue_font_files( 'gravityforms' );

		if ( ! has_action( 'wp_head', array( $loader, 'add_gravityforms_styles' ) ) ) {
			add_action( 'wp_head', array( $loader, 'add_gravityforms_styles' ), 25 );
		}
	}
}
