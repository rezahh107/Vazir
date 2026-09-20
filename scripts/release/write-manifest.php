<?php
declare(strict_types=1);

$options = getopt(
	'',
	[
		'output:', 'mode:', 'source-sha:', 'candidate-sha::', 'version:', 'zip:', 'sha256:',
		'validation:', 'smoke:', 'settings:', 'qualification:', 'publication:', 'run-id:'
	]
);

$required = [ 'output', 'mode', 'source-sha', 'version', 'zip', 'sha256', 'validation', 'smoke', 'settings', 'qualification', 'publication', 'run-id' ];
foreach ( $required as $key ) {
	if ( ! isset( $options[ $key ] ) || '' === (string) $options[ $key ] ) {
		fwrite( STDERR, "Missing required manifest option --{$key}\n" );
		exit( 64 );
	}
}

$allowedModes = [ 'dry-run', 'publish' ];
$allowedPublication = [ 'NOT_ATTEMPTED_DRY_RUN', 'QUALIFIED_NOT_PUBLISHED', 'PUBLISHED', 'PUBLISHED_AND_VERIFIED' ];
if ( ! in_array( $options['mode'], $allowedModes, true ) ) {
	fwrite( STDERR, "Invalid manifest mode.\n" );
	exit( 64 );
}
if ( ! in_array( $options['publication'], $allowedPublication, true ) ) {
	fwrite( STDERR, "Invalid publication state.\n" );
	exit( 64 );
}
if ( ! preg_match( '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/', (string) $options['version'] ) ) {
	fwrite( STDERR, "Invalid manifest version.\n" );
	exit( 64 );
}
if ( ! is_file( $options['zip'] ) ) {
	fwrite( STDERR, "Manifest ZIP does not exist.\n" );
	exit( 1 );
}

$actualSha = hash_file( 'sha256', $options['zip'] );
if ( $actualSha !== $options['sha256'] ) {
	fwrite( STDERR, "Manifest checksum does not match ZIP.\n" );
	exit( 1 );
}

$manifest = [
	'schema_version' => 1,
	'mode' => $options['mode'],
	'source_sha' => $options['source-sha'],
	'candidate_sha' => $options['candidate-sha'] ?? $options['source-sha'],
	'plugin_version' => $options['version'],
	'plugin_entrypoint' => 'vazir-font-wp/vazir-font-wp.php',
	'vazirmatn_release' => 'v33.003',
	'zip_filename' => basename( $options['zip'] ),
	'zip_bytes' => filesize( $options['zip'] ),
	'sha256' => $actualSha,
	'validation' => $options['validation'],
	'clean_install_smoke' => $options['smoke'],
	'admin_settings_artifact' => $options['settings'],
	'product_qualification' => $options['qualification'],
	'publication' => $options['publication'],
	'workflow_run' => $options['run-id'],
];

$output = $options['output'];
$dir = dirname( $output );
if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
	fwrite( STDERR, "Unable to create manifest directory.\n" );
	exit( 1 );
}
$json = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( false === $json || false === file_put_contents( $output, $json . "\n" ) ) {
	fwrite( STDERR, "Unable to write release manifest.\n" );
	exit( 1 );
}
printf( "%s\n", $output );
