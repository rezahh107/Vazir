<?php

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: WordPress is not loaded.\n" );
	exit( 1 );
}

function vazir_wp_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "PASS: {$message}\n" );
}

vazir_wp_assert( defined( 'VAZIR_FONT_VERSION' ), 'plugin bootstrap is active' );
vazir_wp_assert( '1.3.0' === VAZIR_FONT_VERSION, 'runtime version is 1.3.0' );

$options = VazirFontPlugin::get_options();
vazir_wp_assert( ! empty( $options['enable_frontend'] ), 'frontend typography is enabled by default' );
vazir_wp_assert( ! empty( $options['enable_admin'] ), 'admin typography is enabled by default' );
vazir_wp_assert( ! empty( $options['enable_gravity_forms'] ), 'Gravity Forms typography is enabled by default' );
vazir_wp_assert( [ '300', '400', '500', '700', '900' ] === $options['font_weights'], 'default weight schema is preserved' );

$plugin = VazirFontPlugin::get_instance();
vazir_wp_assert( false !== has_action( 'init', [ $plugin, 'load_textdomain' ] ), 'translation loading is deferred to init' );

$loader = VazirFont_Loader::get_instance();
vazir_wp_assert( false !== has_action( 'wp_enqueue_scripts', [ $loader, 'enqueue_frontend_fonts' ] ), 'frontend enqueue hook is registered' );
vazir_wp_assert( false !== has_action( 'admin_enqueue_scripts', [ $loader, 'enqueue_admin_fonts' ] ), 'admin enqueue hook is registered' );
vazir_wp_assert( false !== has_action( 'login_enqueue_scripts', [ $loader, 'enqueue_login_fonts' ] ), 'login enqueue hook is registered' );
vazir_wp_assert( false !== has_action( 'enqueue_block_assets', [ $loader, 'enqueue_editor_content_fonts' ] ), 'editor content uses enqueue_block_assets' );
vazir_wp_assert( false === has_action( 'enqueue_block_editor_assets', [ $loader, 'enqueue_editor_content_fonts' ] ), 'editor content does not rely on enqueue_block_editor_assets' );

do_action( 'wp_enqueue_scripts' );
vazir_wp_assert( wp_style_is( 'vazir-font-frontend', 'enqueued' ), 'frontend style handle enqueues in real WordPress' );

$inline = wp_styles()->get_data( 'vazir-font-frontend', 'after' );
$css    = is_array( $inline ) ? implode( "\n", $inline ) : '';
vazir_wp_assert( 5 === substr_count( $css, '@font-face' ), 'real WordPress receives one font face per configured weight' );
vazir_wp_assert( false !== strpos( $css, "format('woff2')" ), 'real WordPress CSS uses WOFF2' );
vazir_wp_assert( false === strpos( $css, "format('woff')" ), 'real WordPress CSS does not advertise WOFF' );
vazir_wp_assert( false === strpos( $css, "format('truetype')" ), 'real WordPress CSS does not advertise TTF' );
vazir_wp_assert( false !== strpos( $css, "font-family: 'Vazirmatn'" ), 'real WordPress emits the canonical Vazirmatn family' );
foreach ( [ '300', '400', '500', '700', '900' ] as $weight ) {
	vazir_wp_assert( false !== strpos( $css, "vazirmatn-{$weight}.woff2" ), "real WordPress emits the packaged Vazirmatn source for weight {$weight}" );
	vazir_wp_assert( false === strpos( $css, "vazir-{$weight}.woff2" ), "real WordPress does not emit the legacy Vazir source for weight {$weight}" );
}

$legacy_options = [
	'enable_frontend'      => false,
	'enable_admin'         => true,
	'enable_gravity_forms' => false,
	'font_weights'         => [ '400', '700' ],
	'exclude_selectors'    => [ '.legacy-option-boundary' ],
];
update_option( VAZIR_FONT_OPTION_NAME, $legacy_options, false );
VazirFontPlugin::clear_cache();
vazir_wp_assert( $legacy_options === VazirFontPlugin::get_options(), 'existing pre-migration option semantics survive the font migration unchanged' );

$loader->enqueue_admin_fonts();
vazir_wp_assert( wp_style_is( 'vazir-font-admin-runtime', 'enqueued' ), 'admin style handle can enqueue in real WordPress' );

$loader->enqueue_login_fonts();
vazir_wp_assert( wp_style_is( 'vazir-font-login', 'enqueued' ), 'login style handle can enqueue in real WordPress' );

vazir_wp_assert( false === wp_next_scheduled( VAZIR_FONT_LEGACY_CRON_HOOK ), 'legacy weekly cleanup is not scheduled' );
vazir_wp_assert( ! class_exists( 'GFForms' ), 'Gravity Forms is absent in the core smoke lane' );

fwrite( STDOUT, 'WORDPRESS SMOKE PASSED on WordPress ' . get_bloginfo( 'version' ) . "\n" );
