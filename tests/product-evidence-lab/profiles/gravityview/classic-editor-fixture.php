<?php
/**
 * Plugin Name: Vazir GravityView Classic Editor Evidence Fixture
 * Description: Test-only classic-editor post type used by the licensed GravityView evidence lab.
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'vazir_gv_oembed',
			array(
				'label'        => 'Vazir GravityView oEmbed Evidence',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor' ),
			)
		);
	}
);
