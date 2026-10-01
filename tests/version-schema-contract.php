<?php

declare(strict_types=1);

$GLOBALS['vazir_schema_options'] = [
	'vazir_font_db_version' => '1.3.0',
	'vazir_font_options'    => [
		'enable_frontend'      => false,
		'enable_admin'         => true,
		'enable_gravity_forms' => true,
		'font_weights'         => [ '400', '700' ],
		'exclude_selectors'    => [ '.keep-me' ],
	],
];
$GLOBALS['vazir_schema_updates']     = [];
$GLOBALS['vazir_schema_cron_clears'] = 0;

const ABSPATH = '/tmp/wp/';

function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/vazir-font-wp/'; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function load_plugin_textdomain( ...$args ) { return true; }
function plugin_basename( $file ) { return basename( $file ); }
function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['vazir_schema_options'] ) ? $GLOBALS['vazir_schema_options'][ $name ] : $default; }
function update_option( $name, $value ) {
	$GLOBALS['vazir_schema_options'][ $name ] = $value;
	$GLOBALS['vazir_schema_updates'][]        = $name;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	$GLOBALS['vazir_schema_cron_clears']++;
	return 1;
}

function vazir_schema_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

require dirname( __DIR__ ) . '/vazir-font-wp.php';

vazir_schema_assert( '1.5.0' === VAZIR_FONT_VERSION, 'release product version is 1.5.0' );
vazir_schema_assert( '1.3.0' === VAZIR_FONT_SCHEMA_VERSION, 'persisted option schema version remains 1.3.0' );

$plugin     = VazirFontPlugin::get_instance();
$reflection = new ReflectionClass( $plugin );
$migrate    = $reflection->getMethod( 'maybe_migrate_options_schema' );
$migrate->setAccessible( true );

$before = $GLOBALS['vazir_schema_options'];
$migrate->invoke( $plugin );

vazir_schema_assert( $before === $GLOBALS['vazir_schema_options'], '1.3.0 schema state is untouched by the 1.5.0 product release bump' );
vazir_schema_assert( [] === $GLOBALS['vazir_schema_updates'], 'no persisted option write occurs when schema is already current' );
vazir_schema_assert( 0 === $GLOBALS['vazir_schema_cron_clears'], 'no migration cleanup runs solely because the product version changed' );

$GLOBALS['vazir_schema_options']['vazir_font_db_version'] = '1.2.0';
$GLOBALS['vazir_schema_options']['vazir_font_options']['unknown_key'] = 'remove-on-real-schema-migration';
$GLOBALS['vazir_schema_updates']      = [];
$GLOBALS['vazir_schema_cron_clears'] = 0;
$migrate->invoke( $plugin );

vazir_schema_assert( '1.3.0' === $GLOBALS['vazir_schema_options']['vazir_font_db_version'], 'older schema state migrates to the schema version, not the product version' );
vazir_schema_assert( ! array_key_exists( 'unknown_key', $GLOBALS['vazir_schema_options']['vazir_font_options'] ), 'real older-schema migration still normalizes persisted options' );
vazir_schema_assert( in_array( 'vazir_font_options', $GLOBALS['vazir_schema_updates'], true ), 'real older-schema migration writes normalized options' );
vazir_schema_assert( in_array( 'vazir_font_db_version', $GLOBALS['vazir_schema_updates'], true ), 'real older-schema migration records the schema version' );
vazir_schema_assert( 1 === $GLOBALS['vazir_schema_cron_clears'], 'real older-schema migration retains legacy cleanup behavior' );

fwrite( STDOUT, "VERSION SCHEMA CONTRACT PASSED\n" );