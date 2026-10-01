<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$production_files = array(
	'bootstrap'     => $root . '/vazir-font-wp.php',
	'gravityforms' => $root . '/includes/class-vazirfont-gravityforms-integration.php',
	'gravityflow'  => $root . '/includes/class-vazirfont-gravityflow-integration.php',
	'gravityperks' => $root . '/includes/class-vazirfont-gravityperks-integration.php',
	'gravityview'  => $root . '/includes/class-vazirfont-gravityview-integration.php',
);
$integration_classes = array(
	'gravityforms' => 'VazirFont_GravityForms_Integration',
	'gravityflow'  => 'VazirFont_GravityFlow_Integration',
	'gravityperks' => 'VazirFont_GravityPerks_Integration',
	'gravityview'  => 'VazirFont_GravityView_Integration',
);

function vf_version_neutral_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

function vf_token_text( $token ): string {
	return is_array( $token ) ? $token[1] : $token;
}

function vf_normalized_executable_tokens( string $source ): string {
	$normalized = array();
	foreach ( token_get_all( '<?php ' . $source ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_OPEN_TAG === $token[0] || T_WHITESPACE === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$normalized[] = 'T:' . token_name( $token[0] ) . ':' . $token[1];
			continue;
		}
		$normalized[] = 'C:' . $token;
	}
	return implode( "\x1f", $normalized );
}

/**
 * Extract a structural brace body while treating {$var} / ${var} interpolation
 * braces as string syntax rather than executable/class nesting.
 *
 * @param array<int,mixed> $tokens
 * @return array{body:string,end:int}|null
 */
function vf_extract_braced_body( array $tokens, int $body_start ): ?array {
	$count               = count( $tokens );
	$body_depth          = 0;
	$interpolation_depth = 0;
	$body                = '';
	for ( $cursor = $body_start; $cursor < $count; $cursor++ ) {
		$current = $tokens[ $cursor ];
		$body   .= vf_token_text( $current );

		if ( is_array( $current ) && ( T_CURLY_OPEN === $current[0] || T_DOLLAR_OPEN_CURLY_BRACES === $current[0] ) ) {
			$interpolation_depth++;
			continue;
		}
		if ( '}' === $current && $interpolation_depth > 0 ) {
			$interpolation_depth--;
			continue;
		}
		if ( '{' === $current ) {
			$body_depth++;
			continue;
		}
		if ( '}' === $current ) {
			$body_depth--;
			if ( 0 === $body_depth ) {
				return array( 'body' => $body, 'end' => $cursor );
			}
		}
	}
	return null;
}

/** @return array<string,string>|null Method name => complete method body, including braces. */
function vf_extract_class_method_bodies( string $source, string $class_name ): ?array {
	$tokens = token_get_all( $source );
	$count  = count( $tokens );
	for ( $index = 0; $index < $count; $index++ ) {
		$token = $tokens[ $index ];
		if ( ! is_array( $token ) || T_CLASS !== $token[0] ) {
			continue;
		}

		$name = null;
		for ( $cursor = $index + 1; $cursor < $count; $cursor++ ) {
			$candidate = $tokens[ $cursor ];
			if ( is_array( $candidate ) && T_STRING === $candidate[0] ) {
				$name = $candidate[1];
				break;
			}
			if ( '{' === $candidate ) {
				break;
			}
		}
		if ( $class_name !== $name ) {
			continue;
		}

		while ( $cursor < $count && '{' !== $tokens[ $cursor ] ) {
			$cursor++;
		}
		if ( $cursor >= $count ) {
			return null;
		}

		$class_depth = 1;
		$methods     = array();
		for ( $cursor++; $cursor < $count && $class_depth > 0; $cursor++ ) {
			$current = $tokens[ $cursor ];
			if ( '{' === $current ) {
				$class_depth++;
				continue;
			}
			if ( '}' === $current ) {
				$class_depth--;
				continue;
			}
			if ( 1 !== $class_depth || ! is_array( $current ) || T_FUNCTION !== $current[0] ) {
				continue;
			}

			$method_name = null;
			$body_start  = null;
			for ( $method_cursor = $cursor + 1; $method_cursor < $count; $method_cursor++ ) {
				$method_token = $tokens[ $method_cursor ];
				if ( null === $method_name && is_array( $method_token ) && T_STRING === $method_token[0] ) {
					$method_name = $method_token[1];
				}
				if ( '{' === $method_token ) {
					$body_start = $method_cursor;
					break;
				}
				if ( ';' === $method_token ) {
					break;
				}
			}
			if ( null === $method_name || null === $body_start ) {
				return null;
			}

			$extracted = vf_extract_braced_body( $tokens, $body_start );
			if ( null === $extracted ) {
				return null;
			}
			$methods[ $method_name ] = $extracted['body'];
			$cursor                  = $extracted['end'];
		}
		ksort( $methods );
		return $methods;
	}
	return null;
}

/** @return array<string,string>|null Method name => normalized executable-body SHA-256. */
function vf_class_method_lock( string $source, string $class_name ): ?array {
	$bodies = vf_extract_class_method_bodies( $source, $class_name );
	if ( null === $bodies ) {
		return null;
	}
	$lock = array();
	foreach ( $bodies as $method_name => $body ) {
		$lock[ $method_name ] = hash( 'sha256', vf_normalized_executable_tokens( $body ) );
	}
	ksort( $lock );
	return $lock;
}

$sources = array();
foreach ( $production_files as $label => $path ) {
	vf_version_neutral_assert( is_readable( $path ), $label . ' production source is readable' );
	$sources[ $label ] = (string) file_get_contents( $path );
}

$bootstrap_methods = vf_extract_class_method_bodies( $sources['bootstrap'], 'VazirFontPlugin' );
vf_version_neutral_assert( null !== $bootstrap_methods && isset( $bootstrap_methods['init'] ), 'VazirFontPlugin::init() is discoverable for full executable locking' );

$baseline = array(
	'bootstrap_init' => hash( 'sha256', vf_normalized_executable_tokens( $bootstrap_methods['init'] ) ),
	'integrations'   => array(),
);
foreach ( $integration_classes as $label => $class_name ) {
	$lock = vf_class_method_lock( $sources[ $label ], $class_name );
	vf_version_neutral_assert( null !== $lock && array() !== $lock, $class_name . ' declared methods are discoverable for full executable locking' );
	$baseline['integrations'][ $label ] = $lock;
}

fwrite( STDOUT, "FULL_METHOD_LOCK_BASELINE_BEGIN\n" );
fwrite( STDOUT, json_encode( $baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
fwrite( STDOUT, "FULL_METHOD_LOCK_BASELINE_END\n" );
fwrite( STDERR, "FAIL: full executable-method baseline generation is fail-closed until the emitted baseline is reviewed and pinned.\n" );
exit( 1 );
