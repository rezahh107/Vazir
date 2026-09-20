<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$fail = static function ( string $message ): void {
	fwrite( STDERR, "VAZIR_INSTALLED_SMOKE_FAIL: {$message}\n" );
	exit( 1 );
};

if ( ! defined( 'VAZIR_FONT_VERSION' ) || ! class_exists( 'VazirFontPlugin' ) ) {
	$fail( 'Plugin bootstrap identity is unavailable.' );
}
if ( basename( dirname( VAZIR_FONT_PLUGIN_FILE ) ) !== 'vazir-font-wp' ) {
	$fail( 'Plugin is not running from the canonical installed directory.' );
}

$loader = VazirFont_Loader::get_instance();
if ( 5 !== has_action( 'wp_enqueue_scripts', [ $loader, 'enqueue_frontend_fonts' ] ) ) {
	$fail( 'Frontend runtime hook is not initialized.' );
}
if ( 5 !== has_action( 'admin_enqueue_scripts', [ $loader, 'enqueue_admin_fonts' ] ) ) {
	$fail( 'Admin runtime hook is not initialized.' );
}

$admin = VazirFont_Admin_Settings::get_instance();
if ( false === has_action( 'admin_menu', [ $admin, 'add_admin_menu' ] ) ) {
	$fail( 'Settings-page hook is not initialized.' );
}
if ( false === has_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_admin_assets' ] ) ) {
	$fail( 'Settings asset hook is not initialized.' );
}

$required = [
	'assets/css/admin.css',
	'assets/css/vazir-fonts.css',
	'assets/js/admin.js',
	'assets/fonts/Vazirmatn-PROVENANCE.md',
	'assets/fonts/OFL.txt',
	'assets/fonts/AUTHORS.txt',
	'assets/fonts/vazirmatn-300.woff2',
	'assets/fonts/vazirmatn-400.woff2',
	'assets/fonts/vazirmatn-500.woff2',
	'assets/fonts/vazirmatn-700.woff2',
	'assets/fonts/vazirmatn-900.woff2',
];
foreach ( $required as $path ) {
	if ( ! is_readable( VAZIR_FONT_PLUGIN_DIR . $path ) ) {
		$fail( "Installed runtime asset is missing: {$path}" );
	}
}

$css = $loader->get_font_face_css();
foreach ( [ '300', '400', '500', '700', '900' ] as $weight ) {
	if ( false === strpos( $css, "vazirmatn-{$weight}.woff2" ) ) {
		$fail( "Installed runtime CSS does not reference weight {$weight}." );
	}
}

printf( "VAZIR_INSTALLED_SMOKE_PASS version=%s plugin_dir=%s\n", VAZIR_FONT_VERSION, VAZIR_FONT_PLUGIN_DIR );
