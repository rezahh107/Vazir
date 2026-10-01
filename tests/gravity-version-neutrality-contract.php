<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$production_files = array(
	'bootstrap'      => $root . '/vazir-font-wp.php',
	'gravityforms'  => $root . '/includes/class-vazirfont-gravityforms-integration.php',
	'gravityflow'   => $root . '/includes/class-vazirfont-gravityflow-integration.php',
	'gravityperks'  => $root . '/includes/class-vazirfont-gravityperks-integration.php',
	'gravityview'   => $root . '/includes/class-vazirfont-gravityview-integration.php',
);
$evidence_only_versions = array( '3.1.1.1', '3.1.0', '2.3.16', '3.3.4', '1.1.21', '1.5.13' );

function vf_version_neutral_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

foreach ( $production_files as $label => $path ) {
	vf_version_neutral_assert( is_readable( $path ), $label . ' production source is readable' );
	$source = (string) file_get_contents( $path );
	foreach ( $evidence_only_versions as $version ) {
		vf_version_neutral_assert(
			false === strpos( $source, $version ),
			$label . ' production source does not embed evidence-only Gravity version ' . $version
		);
	}
	if ( 'bootstrap' !== $label ) {
		vf_version_neutral_assert(
			0 === preg_match( '/\bversion_compare\s*\(/', $source ),
			$label . ' integration does not use version_compare as production admission'
		);
	}
}

$bootstrap = (string) file_get_contents( $production_files['bootstrap'] );
vf_version_neutral_assert(
	false !== strpos( $bootstrap, "class_exists( 'GFForms' )" )
		&& false !== strpos( $bootstrap, "class_exists( 'Gravity_Flow' )" )
		&& false !== strpos( $bootstrap, "class_exists( 'GravityPerks' )" )
		&& false !== strpos( $bootstrap, "defined( 'GRAVITYVIEW_FILE' )" ),
	'bootstrap admits existing Gravity integrations from runtime capability presence rather than vendor version identity'
);

$flow = (string) file_get_contents( $production_files['gravityflow'] );
vf_version_neutral_assert(
	false !== strpos( $flow, "wp_style_is( \$dependency, 'registered' )" ),
	'Gravity Flow adapter requires the host stylesheet capability at the enqueue boundary'
);

$perks = (string) file_get_contents( $production_files['gravityperks'] );
vf_version_neutral_assert(
	false !== strpos( $perks, "method_exists( 'GWPerksPage', 'load_perk_settings' )" )
		&& false !== strpos( $perks, "wp_style_is( self::HOST_STYLE_HANDLE, 'registered' )" ),
	'Gravity Perks adapter requires callable Settings and registered host-style capabilities'
);

$view = (string) file_get_contents( $production_files['gravityview'] );
vf_version_neutral_assert(
	false !== strpos( $view, 'WP_Block_Type_Registry' )
		&& false !== strpos( $view, "wp_style_is( self::HOST_STYLE_HANDLE, 'registered' )" ),
	'GravityView adapter requires the registered host block/style capabilities'
);

fwrite( STDOUT, "ALL GRAVITY VERSION-NEUTRALITY CONTRACT CHECKS PASSED\n" );
