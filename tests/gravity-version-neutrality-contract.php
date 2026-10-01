<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

/*
 * Primary conformance boundary: every repo-owned executable production surface
 * evidenced to admit, reject, enable, disable, persist, or materially alter
 * Gravity typography application. Executable-token changes fail closed until a
 * reviewer updates the baseline.
 */
$production_boundary = array(
	'bootstrap' => array(
		'path'     => $root . '/vazir-font-wp.php',
		'relative' => 'vazir-font-wp.php',
	),
	'gravityforms' => array(
		'path'     => $root . '/includes/class-vazirfont-gravityforms-integration.php',
		'relative' => 'includes/class-vazirfont-gravityforms-integration.php',
	),
	'gravityflow' => array(
		'path'     => $root . '/includes/class-vazirfont-gravityflow-integration.php',
		'relative' => 'includes/class-vazirfont-gravityflow-integration.php',
	),
	'gravityperks' => array(
		'path'     => $root . '/includes/class-vazirfont-gravityperks-integration.php',
		'relative' => 'includes/class-vazirfont-gravityperks-integration.php',
	),
	'gravityview' => array(
		'path'     => $root . '/includes/class-vazirfont-gravityview-integration.php',
		'relative' => 'includes/class-vazirfont-gravityview-integration.php',
	),
	'selector_boundary' => array(
		'path'     => $root . '/includes/class-vazirfont-selector-boundary.php',
		'relative' => 'includes/class-vazirfont-selector-boundary.php',
	),
	'loader' => array(
		'path'     => $root . '/includes/class-vazirfont-loader.php',
		'relative' => 'includes/class-vazirfont-loader.php',
	),
	'admin_settings' => array(
		'path'     => $root . '/includes/class-vazirfont-admin-settings.php',
		'relative' => 'includes/class-vazirfont-admin-settings.php',
	),
);

/*
 * SHA-256 of the ordered, normalized executable-token stream for the complete
 * boundary above. Normalization removes only whitespace, comments, and
 * docblocks; token names and token text remain part of the identity.
 */
$production_boundary_baseline = '3c78f78427be1275f9be05eba210ac81be4df9abd061d727bfef4c7877d65c7c';

/*
 * Model the structural coverage of the reviewed 2e4af55 boundary before Admin
 * Settings was admitted. Its comparison baseline is derived later from the
 * already-reviewed current production sources so an unrelated release-version
 * mirror change cannot invalidate this structural bypass control.
 */
$previous_2e4af55_boundary = $production_boundary;
unset( $previous_2e4af55_boundary['admin_settings'] );

$legacy_integration_classes = array(
	'gravityforms' => 'VazirFont_GravityForms_Integration',
	'gravityflow'  => 'VazirFont_GravityFlow_Integration',
	'gravityperks' => 'VazirFont_GravityPerks_Integration',
	'gravityview'  => 'VazirFont_GravityView_Integration',
);
$evidence_only_versions = array( '3.1.1.1', '3.1.0', '3.3.4', '2.3.16', '1.1.21', '1.5.13' );

function vf_version_neutral_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

function vf_normalized_executable_tokens( string $source ): string {
	$normalized = '';
	foreach ( token_get_all( $source ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_WHITESPACE === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$normalized .= token_name( $token[0] ) . "\0" . $token[1] . "\n";
			continue;
		}
		$normalized .= "CHAR\0" . $token . "\n";
	}
	return $normalized;
}

/** @param array<string,string> $sources @param array<string,array<string,string>> $boundary */
function vf_production_boundary_hash( array $sources, array $boundary ): string {
	$normalized = '';
	foreach ( $boundary as $label => $definition ) {
		if ( ! isset( $sources[ $label ] ) ) {
			return '';
		}
		$normalized .= "FILE\0" . $definition['relative'] . "\n";
		$normalized .= vf_normalized_executable_tokens( $sources[ $label ] );
	}
	return hash( 'sha256', $normalized );
}

/** @return string[] */
function vf_primary_boundary_violations( array $sources, array $boundary, string $baseline ): array {
	$actual = vf_production_boundary_hash( $sources, $boundary );
	if ( '' === $actual ) {
		return array( 'complete Gravity production boundary could not be normalized' );
	}
	if ( $baseline !== $actual ) {
		return array( 'Gravity production admission/application executable-token boundary changed: expected=' . $baseline . ' actual=' . $actual );
	}
	return array();
}

function vf_token_text( $token ): string {
	return is_array( $token ) ? $token[1] : $token;
}

/** @param array<int,mixed> $tokens @return array{body:string,end:int}|null */
function vf_extract_braced_body( array $tokens, int $body_start ): ?array {
	$count = count( $tokens );
	$body_depth = 0;
	$interpolation_depth = 0;
	$body = '';
	for ( $cursor = $body_start; $cursor < $count; $cursor++ ) {
		$current = $tokens[ $cursor ];
		$body .= vf_token_text( $current );
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

/** @return array<string,string>|null */
function vf_extract_class_method_bodies( string $source, string $class_name ): ?array {
	$tokens = token_get_all( $source );
	$count = count( $tokens );
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
		$methods = array();
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
			$body_start = null;
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
			$methods[ $method_name ] = hash( 'sha256', vf_normalized_executable_tokens( '<?php ' . $extracted['body'] ) );
			$cursor = $extracted['end'];
		}
		ksort( $methods );
		return $methods;
	}
	return null;
}

/** Model the exact structural coverage of the cd97e256 primary lock. */
function vf_legacy_primary_detects_change( array $original, array $mutated, array $integration_classes ): bool {
	$original_bootstrap = vf_extract_class_method_bodies( $original['bootstrap'], 'VazirFontPlugin' );
	$mutated_bootstrap  = vf_extract_class_method_bodies( $mutated['bootstrap'], 'VazirFontPlugin' );
	if ( null === $original_bootstrap || null === $mutated_bootstrap || ! isset( $original_bootstrap['init'], $mutated_bootstrap['init'] ) ) {
		return true;
	}
	if ( $original_bootstrap['init'] !== $mutated_bootstrap['init'] ) {
		return true;
	}
	foreach ( $integration_classes as $label => $class_name ) {
		if ( vf_extract_class_method_bodies( $original[ $label ], $class_name ) !== vf_extract_class_method_bodies( $mutated[ $label ], $class_name ) ) {
			return true;
		}
	}
	return false;
}

/** @return string[] Secondary diagnostics only; not the primary conformance proof. */
function vf_secondary_vendor_identity_violations( array $sources, array $evidence_only_versions ): array {
	$violations = array();
	foreach ( $sources as $label => $source ) {
		$normalized = vf_normalized_executable_tokens( $source );
		foreach ( $evidence_only_versions as $version ) {
			if ( false !== strpos( $normalized, $version ) ) {
				$violations[] = $label . ' production executable contains evidence-only dependency version literal ' . $version;
			}
		}
		foreach ( token_get_all( $source ) as $token ) {
			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}
			$name = $token[1];
			if ( in_array( $name, array( 'VAZIR_FONT_VERSION', 'VAZIR_FONT_SCHEMA_VERSION', 'VAZIR_FONT_DB_VERSION_KEY', 'version_compare' ), true ) ) {
				continue;
			}
			if ( 1 === preg_match( '/(?:version|build[_-]?id)/i', $name ) ) {
				$violations[] = $label . ' production executable references vendor-identity-shaped token ' . $name;
			}
		}
	}
	return array_values( array_unique( $violations ) );
}

function vf_replace_once( string $source, string $search, string $replace, string $label ): string {
	vf_version_neutral_assert( 1 === substr_count( $source, $search ), $label . ' mutation anchor is present exactly once' );
	return str_replace( $search, $replace, $source );
}

function vf_primary_boundary_rejects( array $mutated_sources, array $boundary, string $baseline, string $label ): void {
	$violations = vf_primary_boundary_violations( $mutated_sources, $boundary, $baseline );
	if ( array() !== $violations ) {
		fwrite( STDOUT, 'CONTROL: ' . $label . ' rejected with: ' . implode( ' | ', $violations ) . "\n" );
	}
	vf_version_neutral_assert( array() !== $violations, $label . ' is rejected by the primary executable-token boundary' );
}

function vf_same_root_bypass_rejected( array $original, array $mutated, array $boundary, string $baseline, array $legacy_integration_classes, string $label ): void {
	vf_version_neutral_assert(
		! vf_legacy_primary_detects_change( $original, $mutated, $legacy_integration_classes ),
		$label . ' would evade the previous cd97e256 primary method-container boundary'
	);
	vf_primary_boundary_rejects( $mutated, $boundary, $baseline, $label );
}

$sources = array();
foreach ( $production_boundary as $label => $definition ) {
	vf_version_neutral_assert( is_readable( $definition['path'] ), $label . ' protected production source is readable' );
	$sources[ $label ] = (string) file_get_contents( $definition['path'] );
}

$previous_2e4af55_baseline = vf_production_boundary_hash( $sources, $previous_2e4af55_boundary );
vf_version_neutral_assert(
	'' !== $previous_2e4af55_baseline,
	'previous 2e4af55 structural boundary can be normalized from current reviewed production sources'
);

$current_primary = vf_primary_boundary_violations( $sources, $production_boundary, $production_boundary_baseline );
if ( array() !== $current_primary ) {
	fwrite( STDOUT, 'CURRENT PRIMARY VIOLATIONS: ' . implode( ' | ', $current_primary ) . "\n" );
}
vf_version_neutral_assert( array() === $current_primary, 'current Gravity production admission/application tree matches the complete executable-token boundary' );

$current_secondary = vf_secondary_vendor_identity_violations( $sources, $evidence_only_versions );
if ( array() !== $current_secondary ) {
	fwrite( STDOUT, 'CURRENT SECONDARY DIAGNOSTICS: ' . implode( ' | ', $current_secondary ) . "\n" );
}
vf_version_neutral_assert( array() === $current_secondary, 'secondary Gravity vendor-identity diagnostics remain clean' );

$top_level_bootstrap_mutation = $sources;
$top_level_bootstrap_mutation['bootstrap'] = vf_replace_once(
	$top_level_bootstrap_mutation['bootstrap'],
	"VazirFontPlugin::get_instance();",
	"if ( ! defined( 'GF_BUILD_ID' ) || GF_BUILD_ID >= 99718 ) {\n\tVazirFontPlugin::get_instance();\n}",
	'top-level bootstrap gate'
);
vf_same_root_bypass_rejected( $sources, $top_level_bootstrap_mutation, $production_boundary, $production_boundary_baseline, $legacy_integration_classes, 'top-level VazirFontPlugin::get_instance() Gravity build gate' );

$constructor_mutation = $sources;
$constructor_mutation['bootstrap'] = vf_replace_once(
	$constructor_mutation['bootstrap'],
	"\tprivate function __construct() {\n\t\tregister_activation_hook",
	"\tprivate function __construct() {\n\t\tif ( defined( 'GRAVITY_FLOW_BUILD_ID' ) && GRAVITY_FLOW_BUILD_ID < 99719 ) {\n\t\t\treturn;\n\t\t}\n\t\tregister_activation_hook",
	'pre-init constructor gate'
);
vf_same_root_bypass_rejected( $sources, $constructor_mutation, $production_boundary, $production_boundary_baseline, $legacy_integration_classes, 'pre-init VazirFontPlugin::__construct() Gravity build gate' );

$get_options_mutation = $sources;
$get_options_mutation['bootstrap'] = vf_replace_once(
	$get_options_mutation['bootstrap'],
	"\tpublic static function get_options(): array {\n\t\tif ( null !== self::\$cached_options ) {",
	"\tpublic static function get_options(): array {\n\t\tif ( defined( 'GF_BUILD_ID' ) && GF_BUILD_ID < 99720 ) {\n\t\t\treturn self::get_default_options();\n\t\t}\n\t\tif ( null !== self::\$cached_options ) {",
	'get_options application gate'
);
vf_same_root_bypass_rejected( $sources, $get_options_mutation, $production_boundary, $production_boundary_baseline, $legacy_integration_classes, 'VazirFontPlugin::get_options() Gravity-dependent application gate' );

$selector_boundary_mutation = $sources;
$selector_boundary_mutation['selector_boundary'] = vf_replace_once(
	$selector_boundary_mutation['selector_boundary'],
	"\t): ?array {\n\t\t\$relative = array();",
	"\t): ?array {\n\t\tif ( defined( 'GRAVITYVIEW_BUILD_ID' ) && GRAVITYVIEW_BUILD_ID < 99721 ) {\n\t\t\treturn null;\n\t\t}\n\t\t\$relative = array();",
	'selector-boundary application gate'
);
vf_same_root_bypass_rejected( $sources, $selector_boundary_mutation, $production_boundary, $production_boundary_baseline, $legacy_integration_classes, 'VazirFont_Selector_Boundary Gravity build gate' );

$loader_mutation = $sources;
$loader_mutation['loader'] = vf_replace_once(
	$loader_mutation['loader'],
	"\tpublic function get_font_face_css(): string {\n\t\treturn \$this->generate_font_faces( \$this->get_selected_weights() );",
	"\tpublic function get_font_face_css(): string {\n\t\tif ( defined( 'GRAVITYPERKS_BUILD_ID' ) && GRAVITYPERKS_BUILD_ID < 99722 ) {\n\t\t\treturn '';\n\t\t}\n\t\treturn \$this->generate_font_faces( \$this->get_selected_weights() );",
	'loader application gate'
);
vf_same_root_bypass_rejected( $sources, $loader_mutation, $production_boundary, $production_boundary_baseline, $legacy_integration_classes, 'VazirFont_Loader Gravity Perks build gate' );

$admin_settings_mutation = $sources;
$admin_settings_mutation['admin_settings'] = vf_replace_once(
	$admin_settings_mutation['admin_settings'],
	"\t\t\t\$sanitized[ \$checkbox ] = \$value;\n\t\t}\n\n\t\t\$selected =",
	"\t\t\t\$sanitized[ \$checkbox ] = \$value;\n\t\t}\n\n\t\tif ( defined( 'GF_BUILD_ID' ) && GF_BUILD_ID < 99723 ) {\n\t\t\t\$sanitized['enable_gravity_forms'] = false;\n\t\t}\n\n\t\t\$selected =",
	'Admin Settings persisted Gravity preference gate'
);
vf_version_neutral_assert(
	array() === vf_primary_boundary_violations( $admin_settings_mutation, $previous_2e4af55_boundary, $previous_2e4af55_baseline ),
	'Admin Settings persisted Gravity build gate would evade the previous 2e4af55 primary executable-token boundary'
);
vf_primary_boundary_rejects( $admin_settings_mutation, $production_boundary, $production_boundary_baseline, 'VazirFont_Admin_Settings::sanitize_options() GF_BUILD_ID persistence gate' );

$assignment_mutation = $sources;
$assignment_mutation['gravityforms'] = vf_replace_once(
	$assignment_mutation['gravityforms'],
	"\t\t\$this->gf_available = \$this->is_gravity_forms_active();",
	"\t\t\$this->gf_available = (bool) (\n\t\t\t\$this->is_gravity_forms_active()\n\t\t\t& ( defined( 'GF_BUILD_ID' ) && GF_BUILD_ID >= 99715 )\n\t\t);",
	'assignment/bitwise bypass'
);
vf_primary_boundary_rejects( $assignment_mutation, $production_boundary, $production_boundary_baseline, 'assignment/bitwise GF_BUILD_ID integration gate' );

$helper_body_mutation = $sources;
$helper_body_mutation['gravityflow'] = vf_replace_once(
	$helper_body_mutation['gravityflow'],
	"\t\treturn class_exists( 'Gravity_Flow' );",
	"\t\treturn class_exists( 'Gravity_Flow' ) && ( ! defined( 'GRAVITY_FLOW_BUILD_ID' ) || GRAVITY_FLOW_BUILD_ID >= 99716 );",
	'existing-helper-body bypass'
);
vf_primary_boundary_rejects( $helper_body_mutation, $production_boundary, $production_boundary_baseline, 'existing Gravity Flow helper-body build gate' );

$gf_version_mutation = $sources;
$gf_version_mutation['bootstrap'] = vf_replace_once(
	$gf_version_mutation['bootstrap'],
	"\t\tif ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) ) {",
	"\t\tif ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) && defined( 'GF_VERSION' ) && GF_VERSION >= '99.7.13' ) {",
	'GF_VERSION bootstrap bypass'
);
vf_primary_boundary_rejects( $gf_version_mutation, $production_boundary, $production_boundary_baseline, 'VazirFontPlugin::init() GF_VERSION gate' );

$new_helper_mutation = $sources;
$new_helper_mutation['gravityview'] = vf_replace_once(
	$new_helper_mutation['gravityview'],
	"\t\treturn ! empty( \$options['enable_admin'] ) && ! empty( \$options['enable_gravity_forms'] );",
	"\t\treturn \$this->vendor_build_gate() && ! empty( \$options['enable_admin'] ) && ! empty( \$options['enable_gravity_forms'] );",
	'new-helper caller routing'
);
$new_helper_mutation['gravityview'] = vf_replace_once(
	$new_helper_mutation['gravityview'],
	"\tprivate function has_view_block_editor_style_capability(): bool {",
	"\tprivate function vendor_build_gate(): bool {\n\t\treturn ! defined( 'GRAVITYVIEW_BUILD_ID' ) || GRAVITYVIEW_BUILD_ID >= 99717;\n\t}\n\n\tprivate function has_view_block_editor_style_capability(): bool {",
	'new-helper membership insertion'
);
vf_primary_boundary_rejects( $new_helper_mutation, $production_boundary, $production_boundary_baseline, 'new GravityView helper build gate' );

$strcmp_mutation = $sources;
$strcmp_mutation['bootstrap'] = vf_replace_once(
	$strcmp_mutation['bootstrap'],
	"\t\tif ( class_exists( 'GravityPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' ) ) {",
	"\t\tif ( class_exists( 'GravityPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' ) && strcmp( (string) GravityPerks::\$version, '99.7.14' ) >= 0 ) {",
	'strcmp bootstrap bypass'
);
vf_primary_boundary_rejects( $strcmp_mutation, $production_boundary, $production_boundary_baseline, 'Gravity Perks strcmp vendor-version gate' );

$build_id_mutation = $sources;
$build_id_mutation['gravityflow'] = vf_replace_once(
	$build_id_mutation['gravityflow'],
	"\t\tif ( ! wp_style_is( \$dependency, 'registered' ) ) {",
	"\t\tif ( defined( 'GRAVITY_FLOW_BUILD_ID' ) && GRAVITY_FLOW_BUILD_ID < 99715 ) {\n\t\t\treturn;\n\t\t}\n\n\t\tif ( ! wp_style_is( \$dependency, 'registered' ) ) {",
	'GRAVITY_FLOW_BUILD_ID adapter bypass'
);
vf_primary_boundary_rejects( $build_id_mutation, $production_boundary, $production_boundary_baseline, 'Gravity Flow GRAVITY_FLOW_BUILD_ID adapter gate' );

vf_version_neutral_assert(
	false !== strpos( $sources['admin_settings'], "\$checkboxes = [ 'enable_frontend', 'enable_admin', 'enable_gravity_forms' ];" )
	&& false !== strpos( $sources['admin_settings'], '$sanitized[ $checkbox ] = $value;' ),
	'enable_gravity_forms remains an ordinary persisted Owner preference in Admin Settings'
);

vf_version_neutral_assert(
	false !== strpos( $sources['bootstrap'], "version_compare( \$current_db_version, VAZIR_FONT_SCHEMA_VERSION, '<' )" ),
	"Vazir's persisted-schema VAZIR_FONT_SCHEMA_VERSION/version_compare migration remains present and accepted"
);

$evidence_paths = array(
	$root . '/tests/product-evidence-lab/core/package-identities.sh',
	$root . '/docs/GRAVITY-PERKS-FRONTEND-CHARACTERIZATION.md',
);
$evidence_text = '';
foreach ( $evidence_paths as $path ) {
	vf_version_neutral_assert( is_readable( $path ), basename( $path ) . ' evidence source is readable' );
	$evidence_text .= "\n" . (string) file_get_contents( $path );
}
foreach ( $evidence_only_versions as $version ) {
	vf_version_neutral_assert( false !== strpos( $evidence_text, $version ), 'exact qualified evidence version ' . $version . ' remains permitted outside production admission' );
}

fwrite( STDOUT, "ALL GRAVITY VERSION-NEUTRALITY EXECUTABLE-BOUNDARY CHECKS PASSED\n" );
