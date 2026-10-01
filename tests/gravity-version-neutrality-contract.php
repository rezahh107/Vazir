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
$evidence_only_versions = array( '3.1.1.1', '3.1.0', '2.3.16', '3.3.4', '1.1.21', '1.5.13' );

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

function vf_executable_php( string $source ): string {
	$code = '';
	foreach ( token_get_all( $source ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$code .= $token[1];
			continue;
		}
		$code .= $token;
	}
	return $code;
}

function vf_compact_php( string $source ): string {
	$code = '';
	foreach ( token_get_all( '<?php ' . $source ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_OPEN_TAG === $token[0] || T_WHITESPACE === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$code .= $token[1];
			continue;
		}
		$code .= $token;
	}
	return $code;
}

function vf_extract_method_source( string $source, string $method_name ): ?string {
	$tokens = token_get_all( $source );
	$count  = count( $tokens );
	for ( $index = 0; $index < $count; $index++ ) {
		$token = $tokens[ $index ];
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) {
			continue;
		}

		$name = null;
		for ( $cursor = $index + 1; $cursor < $count; $cursor++ ) {
			$candidate = $tokens[ $cursor ];
			if ( is_array( $candidate ) && T_STRING === $candidate[0] ) {
				$name = $candidate[1];
				break;
			}
			if ( '(' === $candidate ) {
				break;
			}
		}
		if ( $method_name !== $name ) {
			continue;
		}

		while ( $cursor < $count && '{' !== $tokens[ $cursor ] ) {
			$cursor++;
		}
		if ( $cursor >= $count ) {
			return null;
		}

		$depth = 0;
		$body  = '';
		for ( ; $cursor < $count; $cursor++ ) {
			$current = $tokens[ $cursor ];
			$body   .= vf_token_text( $current );
			if ( '{' === $current ) {
				$depth++;
			} elseif ( '}' === $current ) {
				$depth--;
				if ( 0 === $depth ) {
					return $body;
				}
			}
		}
		return null;
	}
	return null;
}

/** @return string[] */
function vf_extract_if_conditions( string $method_source ): array {
	$tokens     = token_get_all( '<?php ' . $method_source );
	$count      = count( $tokens );
	$conditions = array();
	for ( $index = 0; $index < $count; $index++ ) {
		$token = $tokens[ $index ];
		if ( ! is_array( $token ) || ( T_IF !== $token[0] && T_ELSEIF !== $token[0] ) ) {
			continue;
		}

		$cursor = $index + 1;
		while ( $cursor < $count && '(' !== $tokens[ $cursor ] ) {
			$cursor++;
		}
		if ( $cursor >= $count ) {
			continue;
		}

		$depth     = 0;
		$condition = '';
		for ( ; $cursor < $count; $cursor++ ) {
			$current = $tokens[ $cursor ];
			if ( '(' === $current ) {
				$depth++;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $current ) {
				$depth--;
				if ( 0 === $depth ) {
					break;
				}
			}
			$condition .= vf_token_text( $current );
		}
		$conditions[] = $condition;
	}
	return $conditions;
}

/** @return string[] */
function vf_extract_return_expressions( string $method_source ): array {
	$tokens  = token_get_all( '<?php ' . $method_source );
	$count   = count( $tokens );
	$returns = array();
	for ( $index = 0; $index < $count; $index++ ) {
		$token = $tokens[ $index ];
		if ( ! is_array( $token ) || T_RETURN !== $token[0] ) {
			continue;
		}
		$expression = '';
		for ( $cursor = $index + 1; $cursor < $count; $cursor++ ) {
			$current = $tokens[ $cursor ];
			if ( ';' === $current ) {
				break;
			}
			$expression .= vf_token_text( $current );
		}
		$returns[] = $expression;
	}
	return $returns;
}

/** @param string[] $expressions @return string[] */
function vf_compact_expressions( array $expressions ): array {
	return array_map( 'vf_compact_php', $expressions );
}

function vf_logical_operator_count( string $source ): int {
	$count = 0;
	foreach ( token_get_all( '<?php ' . $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			continue;
		}
		if ( T_BOOLEAN_AND === $token[0] || T_BOOLEAN_OR === $token[0] || T_LOGICAL_AND === $token[0] || T_LOGICAL_OR === $token[0] ) {
			$count++;
		}
	}
	return $count;
}

function vf_ternary_count( string $source ): int {
	$count = 0;
	foreach ( token_get_all( '<?php ' . $source ) as $token ) {
		if ( '?' === $token ) {
			$count++;
		}
	}
	return $count;
}

function vf_disallowed_control_count( string $source ): int {
	$count = 0;
	foreach ( token_get_all( '<?php ' . $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			continue;
		}
		if ( T_SWITCH === $token[0] || T_WHILE === $token[0] || T_FOR === $token[0] || T_FOREACH === $token[0] || T_DO === $token[0] ) {
			$count++;
		}
	}
	return $count;
}

/**
 * Admission-boundary structure is fail-closed. Every current predicate is
 * explicitly admitted; a new/changed predicate fails regardless of vendor
 * identifier, literal, or comparison mechanism. Logical short-circuit and
 * alternate control-flow counts prevent a gate being moved outside an if.
 *
 * @param string[]      $expected_conditions
 * @param string[]|null $expected_returns
 */
function vf_method_predicate_structure_matches(
	string $source,
	string $method_name,
	array $expected_conditions,
	?array $expected_returns = null,
	int $expected_ternaries = 0
): bool {
	$method = vf_extract_method_source( $source, $method_name );
	if ( null === $method ) {
		return false;
	}

	if ( vf_compact_expressions( vf_extract_if_conditions( $method ) ) !== vf_compact_expressions( $expected_conditions ) ) {
		return false;
	}

	if ( null !== $expected_returns && vf_compact_expressions( vf_extract_return_expressions( $method ) ) !== vf_compact_expressions( $expected_returns ) ) {
		return false;
	}

	$expected_logical = 0;
	foreach ( $expected_conditions as $expression ) {
		$expected_logical += vf_logical_operator_count( $expression );
	}
	if ( null !== $expected_returns ) {
		foreach ( $expected_returns as $expression ) {
			$expected_logical += vf_logical_operator_count( $expression );
		}
	}

	if ( vf_logical_operator_count( $method ) !== $expected_logical ) {
		return false;
	}
	if ( vf_ternary_count( $method ) !== $expected_ternaries ) {
		return false;
	}
	if ( 0 !== vf_disallowed_control_count( $method ) ) {
		return false;
	}

	return true;
}

/**
 * Capability-only admission permits only the declared runtime capability leaves
 * joined by logical AND, with optional grouping parentheses. Any additional
 * operand, comparison, literal, function call, or alternative operator fails.
 *
 * @param string[] $allowed_capabilities
 */
function vf_is_capability_only_expression( string $expression, array $allowed_capabilities ): bool {
	$work = vf_compact_php( $expression );
	foreach ( $allowed_capabilities as $capability ) {
		$needle = vf_compact_php( $capability );
		if ( 1 !== substr_count( $work, $needle ) ) {
			return false;
		}
		$work = str_replace( $needle, '#CAP#', $work );
	}

	$work = str_replace( array( '#CAP#', '&&', 'and', '(', ')' ), '', $work );
	return '' === $work;
}

/** @return string[] */
function vf_non_vazir_version_signals( string $source ): array {
	$signals      = array();
	$parse_source = preg_match( '/^\s*<\?php\b/', $source ) ? $source : '<?php ' . $source;
	foreach ( token_get_all( $parse_source ) as $token ) {
		if ( ! is_array( $token ) ) {
			continue;
		}
		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] || T_WHITESPACE === $token[0] ) {
			continue;
		}

		$text = $token[1];
		if ( false === stripos( $text, 'version' ) ) {
			continue;
		}
		if ( false !== stripos( $text, 'VAZIR_FONT_VERSION' ) || false !== stripos( $text, 'VAZIR_FONT_SCHEMA_VERSION' ) ) {
			continue;
		}
		$signals[] = $text;
	}
	return array_values( array_unique( $signals ) );
}

/**
 * @param array<string,string> $sources
 * @param string[]             $evidence_only_versions
 * @return string[]
 */
function vf_gravity_admission_violations( array $sources, array $evidence_only_versions ): array {
	$violations = array();

	// Secondary defense: qualified versions belong in evidence, never production.
	foreach ( $sources as $label => $source ) {
		$executable = vf_executable_php( $source );
		foreach ( $evidence_only_versions as $version ) {
			if ( false !== strpos( $executable, $version ) ) {
				$violations[] = $label . ' executable production code embeds evidence-only Gravity version ' . $version;
			}
		}
	}

	$bootstrap_init = vf_extract_method_source( $sources['bootstrap'], 'init' );
	if ( null === $bootstrap_init ) {
		$violations[] = 'bootstrap init admission method is missing or unreadable';
	} else {
		$bootstrap_signals = vf_non_vazir_version_signals( $bootstrap_init );
		if ( array() !== $bootstrap_signals ) {
			$violations[] = 'bootstrap init admission inspects version identity: ' . implode( ', ', $bootstrap_signals );
		}

		$conditions = vf_extract_if_conditions( $bootstrap_init );
		$bootstrap_contracts = array(
			'Gravity Forms' => array(
				'marker'  => "class_exists( 'VazirFont_GravityForms_Integration' )",
				'allowed' => array( "class_exists( 'GFForms' )", "class_exists( 'VazirFont_GravityForms_Integration' )" ),
			),
			'Gravity Flow' => array(
				'marker'  => "class_exists( 'VazirFont_GravityFlow_Integration' )",
				'allowed' => array( "class_exists( 'Gravity_Flow' )", "class_exists( 'VazirFont_GravityFlow_Integration' )" ),
			),
			'Gravity Perks' => array(
				'marker'  => "class_exists( 'VazirFont_GravityPerks_Integration' )",
				'allowed' => array( "class_exists( 'GravityPerks' )", "class_exists( 'VazirFont_GravityPerks_Integration' )" ),
			),
			'GravityView' => array(
				'marker'  => "class_exists( 'VazirFont_GravityView_Integration' )",
				'allowed' => array( "defined( 'GRAVITYVIEW_FILE' )", "class_exists( 'VazirFont_GravityView_Integration' )" ),
			),
		);

		foreach ( $bootstrap_contracts as $product => $contract ) {
			$matching = array();
			$marker   = vf_compact_php( $contract['marker'] );
			foreach ( $conditions as $condition ) {
				if ( false !== strpos( vf_compact_php( $condition ), $marker ) ) {
					$matching[] = $condition;
				}
			}
			if ( 1 !== count( $matching ) || ! vf_is_capability_only_expression( $matching[0], $contract['allowed'] ) ) {
				$violations[] = 'bootstrap ' . $product . ' admission is not capability-only';
			}
		}
	}

	$runtime_capability_contracts = array(
		'gravityforms' => array(
			'method'  => 'is_gravity_forms_active',
			'allowed' => array( "class_exists( 'GFForms' )", "class_exists( 'GFCommon' )" ),
		),
		'gravityflow' => array(
			'method'  => 'is_gravity_flow_runtime_available',
			'allowed' => array( "class_exists( 'Gravity_Flow' )" ),
		),
		'gravityperks' => array(
			'method'  => 'is_gravity_perks_runtime_available',
			'allowed' => array( "class_exists( 'GravityPerks' )" ),
		),
		'gravityview' => array(
			'method'  => 'is_gravityview_runtime_available',
			'allowed' => array( "defined( 'GRAVITYVIEW_FILE' )" ),
		),
	);

	foreach ( $runtime_capability_contracts as $label => $contract ) {
		$method = vf_extract_method_source( $sources[ $label ], $contract['method'] );
		if ( null === $method ) {
			$violations[] = $label . ' runtime capability admission method is missing or unreadable';
			continue;
		}

		$returns = vf_extract_return_expressions( $method );
		if ( 1 !== count( $returns ) || ! vf_is_capability_only_expression( $returns[0], $contract['allowed'] ) ) {
			$violations[] = $label . ' runtime admission is not capability-only';
		}
	}

	// Primary defect-class closure: bind the executable predicate structure of
	// the production bootstrap plus every Gravity runtime/admission/application
	// boundary. A new gate fails even if its identifier never says "version".
	$boundary_contracts = array(
		'bootstrap' => array(
			'init' => array(
				'conditions' => array(
					"class_exists( 'VazirFont_Loader' )",
					"is_admin() && class_exists( 'VazirFont_Admin_Settings' )",
					"class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' )",
					"class_exists( 'Gravity_Flow' ) && class_exists( 'VazirFont_GravityFlow_Integration' )",
					"class_exists( 'GravityPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' )",
					"defined( 'GRAVITYVIEW_FILE' ) && class_exists( 'VazirFont_GravityView_Integration' )",
				),
			),
		),
		'gravityforms' => array(
			'__construct' => array( 'conditions' => array( '$this->gf_available' ) ),
			'is_gravity_forms_active' => array(
				'conditions' => array(),
				'returns' => array( "class_exists( 'GFForms' ) && class_exists( 'GFCommon' )" ),
			),
			'init_hooks' => array( 'conditions' => array() ),
			'enqueue_gravityforms_assets' => array( 'conditions' => array( '! $this->is_enabled()' ) ),
			'mark_preview_request' => array( 'conditions' => array( '! $this->is_enabled()' ) ),
			'enqueue_gravityforms_admin_assets' => array( 'conditions' => array( '! $this->is_enabled() || ! $this->is_gravity_forms_admin_screen()' ) ),
			'filter_preview_styles' => array( 'conditions' => array( '! $this->is_enabled()' ) ),
			'add_noconflict_styles' => array(
				'conditions' => array( '! $this->is_enabled()', "! empty( \$options['enable_admin'] )" ),
			),
			'add_field_css_class' => array( 'conditions' => array( '$this->is_enabled()' ) ),
			'remove_inline_font_styles' => array( 'conditions' => array( '! $this->is_enabled() || [] !== $this->get_negative_scope_selectors()' ) ),
			'enqueue_style' => array( 'conditions' => array( '! $this->inline_attached' ) ),
			'register_style' => array( 'conditions' => array( '$this->style_registered' ) ),
			'is_enabled' => array(
				'conditions' => array(),
				'returns'    => array( "! empty( \$options['enable_gravity_forms'] )" ),
			),
			'is_gravity_forms_admin_screen' => array(
				'conditions' => array(
					"class_exists( 'GFForms' ) && method_exists( 'GFForms', 'is_gravity_page' )",
					"class_exists( 'RGForms' ) && method_exists( 'RGForms', 'is_gravity_page' )",
					"! function_exists( 'get_current_screen' )",
					'! $screen instanceof WP_Screen',
				),
				'returns' => array(
					'(bool) GFForms::is_gravity_page()',
					'(bool) RGForms::is_gravity_page()',
					'false',
					'false',
					"false !== strpos( \$screen->id, 'gf_' ) || false !== strpos( \$screen->id, 'gravityforms' )",
				),
			),
		),
		'gravityflow' => array(
			'__construct' => array( 'conditions' => array( '$this->flow_available' ) ),
			'is_gravity_flow_runtime_available' => array(
				'conditions' => array(),
				'returns' => array( "class_exists( 'Gravity_Flow' )" ),
			),
			'init_hooks' => array( 'conditions' => array() ),
			'enqueue_admin_assets' => array( 'conditions' => array() ),
			'enqueue_frontend_assets' => array( 'conditions' => array() ),
			'enqueue_style' => array(
				'conditions' => array(
					'! $this->is_enabled( $context )',
					"! wp_style_is( \$dependency, 'registered' )",
					'isset( $this->inline_handles[ $handle ] )',
					"'' !== trim( \$css )",
				),
			),
			'is_enabled' => array(
				'conditions' => array(
					"empty( \$options['enable_gravity_forms'] )",
					"'admin' === \$context",
					"'frontend' === \$context",
				),
				'returns' => array(
					'false',
					"! empty( \$options['enable_admin'] )",
					"! empty( \$options['enable_frontend'] )",
					'false',
				),
			),
		),
		'gravityperks' => array(
			'__construct' => array( 'conditions' => array( '$this->perks_available' ) ),
			'is_gravity_perks_runtime_available' => array(
				'conditions' => array(),
				'returns' => array( "class_exists( 'GravityPerks' )" ),
			),
			'filter_print_styles_array' => array(
				'conditions' => array(
					'$this->inline_attached || ! $this->is_enabled() || ! $this->is_standalone_settings_request( $handles )',
					"'' === trim( \$css )",
					'wp_add_inline_style( self::HOST_STYLE_HANDLE, $css )',
				),
			),
			'is_enabled' => array(
				'conditions' => array(),
				'returns' => array( "! empty( \$options['enable_admin'] ) && ! empty( \$options['enable_gravity_forms'] )" ),
			),
			'is_standalone_settings_request' => array(
				'conditions' => array(
					"! is_admin() || ! \$this->perks_available || ! class_exists( 'GWPerksPage' ) || ! method_exists( 'GWPerksPage', 'load_perk_settings' )",
					"'gwp_perks' !== \$page || '' === \$view || '' === \$slug",
					'! in_array( self::HOST_STYLE_HANDLE, $handles, true )',
				),
				'returns' => array( 'false', 'false', 'false', "wp_style_is( self::HOST_STYLE_HANDLE, 'registered' )" ),
				'ternaries' => 3,
			),
		),
		'gravityview' => array(
			'__construct' => array( 'conditions' => array( '$this->gravityview_available' ) ),
			'is_gravityview_runtime_available' => array(
				'conditions' => array(),
				'returns' => array( "defined( 'GRAVITYVIEW_FILE' )" ),
			),
			'enqueue_editor_typography' => array(
				'conditions' => array(
					'$this->inline_attached || ! $this->is_enabled() || ! $this->has_view_block_editor_style_capability()',
					"'' === trim( \$css )",
					'wp_add_inline_style( self::HOST_STYLE_HANDLE, $css )',
				),
			),
			'is_enabled' => array(
				'conditions' => array(),
				'returns' => array( "! empty( \$options['enable_admin'] ) && ! empty( \$options['enable_gravity_forms'] )" ),
			),
			'has_view_block_editor_style_capability' => array(
				'conditions' => array(
					"! is_admin() || ! \$this->gravityview_available || ! class_exists( 'WP_Block_Type_Registry' ) || ! class_exists( 'WP_Block_Type' )",
					'! $block_type instanceof WP_Block_Type',
					'! in_array( self::HOST_STYLE_HANDLE, (array) $block_type->editor_style_handles, true )',
				),
				'returns' => array( 'false', 'false', 'false', "wp_style_is( self::HOST_STYLE_HANDLE, 'registered' )" ),
			),
		),
	);

	foreach ( $boundary_contracts as $label => $methods ) {
		foreach ( $methods as $method_name => $contract ) {
			$returns   = array_key_exists( 'returns', $contract ) ? $contract['returns'] : null;
			$ternaries = isset( $contract['ternaries'] ) ? (int) $contract['ternaries'] : 0;
			if ( ! vf_method_predicate_structure_matches( $sources[ $label ], $method_name, $contract['conditions'], $returns, $ternaries ) ) {
				$violations[] = $label . ' ' . $method_name . ' admission predicate structure changed outside the capability contract';
			}
		}
	}

	// Secondary lexical tripwire: useful diagnostics for obvious version APIs,
	// but no longer the proof of defect-class closure.
	foreach ( array( 'gravityforms', 'gravityflow', 'gravityperks', 'gravityview' ) as $label ) {
		$signals = vf_non_vazir_version_signals( $sources[ $label ] );
		if ( array() !== $signals ) {
			$violations[] = $label . ' integration executable code inspects version identity: ' . implode( ', ', $signals );
		}
	}

	return array_values( array_unique( $violations ) );
}

/**
 * @param array<string,string> $sources
 * @param string[]             $evidence_only_versions
 */
function vf_mutation_is_rejected( array $sources, array $evidence_only_versions, string $label ): bool {
	$violations = vf_gravity_admission_violations( $sources, $evidence_only_versions );
	if ( array() === $violations ) {
		fwrite( STDERR, "Mutation unexpectedly passed: {$label}\n" );
		return false;
	}
	fwrite( STDOUT, 'CONTROL: ' . $label . ' rejected with: ' . implode( ' | ', $violations ) . "\n" );
	return true;
}

$sources = array();
foreach ( $production_files as $label => $path ) {
	vf_version_neutral_assert( is_readable( $path ), $label . ' production source is readable' );
	$sources[ $label ] = (string) file_get_contents( $path );
}

$current_violations = vf_gravity_admission_violations( $sources, $evidence_only_versions );
vf_version_neutral_assert(
	array() === $current_violations,
	'current Gravity production admission is capability-based and version-neutral' . ( array() === $current_violations ? '' : ': ' . implode( ' | ', $current_violations ) )
);

$schema_logic = vf_compact_php( $sources['bootstrap'] );
vf_version_neutral_assert(
	false !== strpos( $schema_logic, "version_compare(\$current_db_version,VAZIR_FONT_SCHEMA_VERSION,'<')" ),
	"Vazir's own persisted-schema version_compare migration remains legitimate and outside Gravity admission"
);

$bootstrap_mutation = $sources;
$bootstrap_needle   = "if ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) ) {";
$bootstrap_gate     = "if ( class_exists( 'GFForms' ) && defined( 'GF_VERSION' ) && GF_VERSION >= '99.7.13' && class_exists( 'VazirFont_GravityForms_Integration' ) ) {";
vf_version_neutral_assert( false !== strpos( $bootstrap_mutation['bootstrap'], $bootstrap_needle ), 'bootstrap mutation anchor is present' );
$bootstrap_mutation['bootstrap'] = str_replace( $bootstrap_needle, $bootstrap_gate, $bootstrap_mutation['bootstrap'], $bootstrap_replacements );
vf_version_neutral_assert( 1 === $bootstrap_replacements, 'bootstrap bypass mutation was applied exactly once' );
vf_version_neutral_assert(
	vf_mutation_is_rejected( $bootstrap_mutation, $evidence_only_versions, 'new/non-evidence GF vendor-version gate retained beside class_exists capability markers' ),
	'bootstrap bypass falsification is rejected deterministically'
);

$alternate_mutation = $sources;
$alternate_needle   = "if ( class_exists( 'GravityPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' ) ) {";
$alternate_gate     = "if ( class_exists( 'GravityPerks' ) && strcmp( (string) GravityPerks::\$version, '99.7.14' ) >= 0 && class_exists( 'VazirFont_GravityPerks_Integration' ) ) {";
vf_version_neutral_assert( false !== strpos( $alternate_mutation['bootstrap'], $alternate_needle ), 'alternate-comparison mutation anchor is present' );
$alternate_mutation['bootstrap'] = str_replace( $alternate_needle, $alternate_gate, $alternate_mutation['bootstrap'], $alternate_replacements );
vf_version_neutral_assert( 1 === $alternate_replacements, 'alternate-comparison mutation was applied exactly once' );
vf_version_neutral_assert(
	vf_mutation_is_rejected( $alternate_mutation, $evidence_only_versions, 'Gravity Perks vendor-version gate using strcmp instead of version_compare' ),
	'alternate-comparison falsification is rejected deterministically'
);

$adapter_mutation = $sources;
$adapter_needle   = "if ( ! wp_style_is( \$dependency, 'registered' ) ) {";
$adapter_gate     = "if ( defined( 'GRAVITY_FLOW_BUILD_ID' ) && GRAVITY_FLOW_BUILD_ID >= 99715 ) {\n\t\t\treturn;\n\t\t}\n\n\t\tif ( ! wp_style_is( \$dependency, 'registered' ) ) {";
vf_version_neutral_assert( false !== strpos( $adapter_mutation['gravityflow'], $adapter_needle ), 'adapter-boundary mutation anchor is present' );
$adapter_mutation['gravityflow'] = str_replace( $adapter_needle, $adapter_gate, $adapter_mutation['gravityflow'], $adapter_replacements );
vf_version_neutral_assert( 1 === $adapter_replacements, 'adapter-boundary mutation was applied exactly once' );
vf_version_neutral_assert(
	array() === vf_non_vazir_version_signals( $adapter_mutation['gravityflow'] ),
	'adapter mutation intentionally uses vendor build identity with no version-named token, so lexical detection cannot prove rejection'
);
vf_version_neutral_assert(
	vf_mutation_is_rejected( $adapter_mutation, $evidence_only_versions, 'Gravity Flow enqueue admission gated by vendor build identity while host capability check remains present' ),
	'adapter-boundary falsification is rejected by admission predicate structure'
);

$evidence_doc      = $root . '/docs/GRAVITY-PERKS-FRONTEND-CHARACTERIZATION.md';
$evidence_identity = $root . '/tests/product-evidence-lab/core/package-identities.sh';
vf_version_neutral_assert( is_readable( $evidence_doc ) && is_readable( $evidence_identity ), 'version-bound evidence documentation and identity sources are readable' );
$evidence_text = (string) file_get_contents( $evidence_doc ) . "\n" . (string) file_get_contents( $evidence_identity );
vf_version_neutral_assert(
	false !== strpos( $evidence_text, '1.1.21' ) && false !== strpos( $evidence_text, '1.5.13' ) && false !== strpos( $evidence_text, '3.1.1.1' ),
	'exact qualified Gravity/Perk versions remain valid in evidence/documentation outside production admission'
);

fwrite( STDOUT, "ALL GRAVITY VERSION-NEUTRALITY CONTRACT CHECKS PASSED\n" );
