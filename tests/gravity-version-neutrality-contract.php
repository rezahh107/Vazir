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

/*
 * Primary conformance baseline. Any executable change to VazirFontPlugin::init()
 * or any declared method in a Gravity integration class requires an explicit
 * review/update of this version-neutrality contract.
 *
 * These hashes are over normalized PHP token names + token text, excluding only
 * whitespace/comments/docblocks. They were cross-checked on PHP 7.4 and 8.3.
 */
$full_method_lock_baseline = array(
	'bootstrap_init' => '5d97510b375b5b7c81be8e6336e6f3dc27f84fb7e016a034fb94834b1421b55a',
	'integrations'   => array(
		'gravityforms' => array(
			'__clone'                           => '9953918e2110cad90f52a4797ffee99372b03d9fcfe0978b7d48d877b5ce122c',
			'__construct'                       => '6ebf254068fb78c5be2479d274c6b25601488429bf34f4317d38d85ef9c76bc2',
			'__wakeup'                          => 'f97c922e64d6f490c07e2dd216a207f081341392af52a3a944940141a8d63311',
			'add_field_css_class'                => '283f46c5b12a315054a987b1dabebdc21a9a38b7bce217de32548fafcb8d0732',
			'add_noconflict_styles'              => 'b964ca16b73303ea2ddab837f69122c81199bc753396ae3601a2afe4bb22659d',
			'apply_exclusion_boundary'           => '3766adabbdef7d3fac9ba9e303dd89c50c9092aebed3d8d568739924bb9095f4',
			'build_enforcement_selector_list'    => 'a542d8b9f645cce9d4480694ee3612b9907535e9f290cf228e2da24001f6a819',
			'enqueue_gravityforms_admin_assets'  => '1ac046fffe001156410a78e53b7b8522ed684757ce62acfddf9e3cd4ad93b159',
			'enqueue_gravityforms_assets'        => '430ca3f02ac39076d3d41442cc2a4df6524afd27be391f17b4bde135a7d7f776',
			'enqueue_style'                      => '42d2daa74114c439c4e4431908baad2014f29005310458900699902f81fa2dbf',
			'filter_preview_styles'              => '97fe3ef1d616b1f3d06c1e84fe220795a32911628c33a698dbf0ba3a2bf46e1e',
			'get_gravityforms_css'               => '0a723dd7efff9cd97920c2332d946ddb287b4c57feacd599867204a94c78c9c2',
			'get_instance'                       => 'e31da18519b543f7cfd47e26ba1f8c9dc1157f37f780a699acc11570bfb411ca',
			'get_negative_scope_selectors'       => '5ac3f2172d47df9b86700a0c8f7b88b34bcef9d5fa236b836249ecdb498e1bba',
			'init_hooks'                         => 'ae8bbbe17566cffa0333a12cf9624ce934d4654f9755f15444f65ab8ca9a3582',
			'is_enabled'                         => 'f041fd33e8a52724c5fb296135f7dbeeaf4cdb50fe37fefa16302468d3348feb',
			'is_gravity_forms_active'            => '2a9381e0aface38c1f7e728b72743e9c3259a862027691d444fb536cddf1b4ed',
			'is_gravity_forms_admin_screen'      => '33a45776be3695a5191c2174f09c5d6b27f4ffd1084bdf32bb542aa06f7c1bdb',
			'is_valid_css_selector'              => '84b92f29280fa3e5c801a9832fae29268ea1ec39cc52286a9c976c5a6e0e75c4',
			'mark_preview_request'               => '729fed57fcc41093aac54a9e2b036afbb888ca0ba3239d8c0a06443f1b546d32',
			'register_style'                     => '20ccb7006fba7754c749d7ebbc16a92e5ff520d82d0f54451f99ef70c24666ec',
			'remove_inline_font_styles'          => '8dfd4d9d516efd6a55b9d1c45254b8d877d105bef42da7be32ff8ab69192ecca',
			'sanitize_css_selector'              => '61e2c851e3e7e159dbf6d51e8f06fb47c07dc6cad74516f22d05ceae0a2c269a',
			'selector_targets_pseudo_element'    => '0b2d2ea5212649e7d3e8a46c8e1c0727e774bdbe353899302f953c8b2db6fb75',
			'split_top_level_selector_list'      => 'b5f666c9d5749ed57b650f76b921011d595dfa3c2358d33d5f80c1b27ad6b311',
		),
		'gravityflow' => array(
			'__clone'                        => '9953918e2110cad90f52a4797ffee99372b03d9fcfe0978b7d48d877b5ce122c',
			'__construct'                    => '1dcebc35eef3cb0bfdc85db50ee47fb9098bc35d76be9743e95995459c9d291d',
			'__wakeup'                       => '3751464c8c6e62be76007bccf3aa10ddda74def9259d4d78ee59b322c250d4ad',
			'apply_exclusion_boundary'        => 'd96e5574420812d1a3e7cf80eb2585bc2f90f48106b45c61b09d109ec5979058',
			'apply_portal_exclusion_boundary' => 'a26d781b912e0128c19ed8fae673a886f01b59e3d38001110d27ff3b1f50f600',
			'build_blocked_selector_list'     => '4724b2cf28213e1d9a4f4972f2c3bace65200d84e85f6e0995052622cd92b3d0',
			'build_enforcement_selector_list' => 'ad1d5f7319ff1e49644be9aa7d22337f82914d51436c09a46cef49b36688313e',
			'contains_relational_exclusion'   => '4fa9251f7159ec4e8a3f839ea88a34e5d3bdc77f1adc3a7953b2d8fe4e9281a2',
			'enqueue_admin_assets'             => '604512a599a2d1d86b81e72362e320f497c0ee2d00289cf32fd8753ca90257b1',
			'enqueue_frontend_assets'          => '31eb53603eb5e655a482cc2f09cf122cb94256a8260c57e1675a6af00cae205a',
			'enqueue_style'                    => 'b44c7db0f02a183aeb17e2807a137c6a95cad47e034aa6b03fee4c406c2a51b8',
			'get_gravityflow_css'              => 'ab94b43f7ba34ec0a57c418e31d4f905da49726eb8873379b4409bd47918ab99',
			'get_instance'                     => 'e31da18519b543f7cfd47e26ba1f8c9dc1157f37f780a699acc11570bfb411ca',
			'get_negative_scope_selectors'     => 'f5eb02644227425f8b4b0f53acaf09d6f95dc777ed9f62eaa7839a4a1c42c4d5',
			'init_hooks'                       => 'c0de27799c9d2178cdebd04c68397bba81ccff31e702705d26450c39f67e6be7',
			'is_enabled'                       => '6434f4771008cb00fd713f5442ad853b528f2da16e5fa443dd9b372af22297af',
			'is_gravity_flow_runtime_available'=> 'd3d6da94a2703e356a58635148ec63c4f5c6b3de7e25de3ff324d37685d39c66',
			'is_valid_css_selector'            => '84b92f29280fa3e5c801a9832fae29268ea1ec39cc52286a9c976c5a6e0e75c4',
			'sanitize_css_selector'            => '5393bd1d46e17d7a795d68a91af87ac04cac4b51be6c508cd65bfeea7adfffc2',
			'selector_targets_pseudo_element'  => '0b2d2ea5212649e7d3e8a46c8e1c0727e774bdbe353899302f953c8b2db6fb75',
			'split_top_level_selector_list'    => 'fff7ef51dc36fbbecf6a211e017a3350da4292673a39b72fe8823d2f046980b2',
		),
		'gravityperks' => array(
			'__clone'                      => '9953918e2110cad90f52a4797ffee99372b03d9fcfe0978b7d48d877b5ce122c',
			'__construct'                  => 'ddf637fb1b2e7c4b667993561f9601a98f95a10ea9eeb69fcddbd2812b48e9e3',
			'__wakeup'                     => 'ea968ab2a25383be1f6606ad7a4cabaaccbab800efd1acccd2e36a378ec3045b',
			'apply_exclusion_boundary'      => '96b5718bc074996041f92676eee3109f61ec66cc7446f5381b9940b5678060c0',
			'build_enforcement_selector_list'=> '49bb1ad3847800b2b4a8892ba710db8c9cadee950bc156b9cba586119f6f1431',
			'filter_print_styles_array'      => '94d479b20fdb91b54a5a419fedc2eb9999f793673683e59c3c1a6ed2ac1d3367',
			'get_instance'                   => 'e31da18519b543f7cfd47e26ba1f8c9dc1157f37f780a699acc11570bfb411ca',
			'get_negative_scope_selectors'   => '2d6c94a1026f42772775c0856131639ebf55c7dfea02d3244bf8da580bb55532',
			'get_settings_css'               => '762a85f69b9555cf7ad0747b3188cdd8c07598a52b8b91430a293b09a72ffedf',
			'is_enabled'                     => 'b984cbf7e7030a6ab5fe4766fdb0e2516afc727493ab6970758cef09dc1c6dbd',
			'is_gravity_perks_runtime_available'=> '7ccdf744afe5c91526172d69ea7d01f32d7810624bc800d31d91342d46fa7c2d',
			'is_standalone_settings_request' => 'a0a311691fbed275169e76ccf09c8f708e2cceba7819e962380b56baed6b73e2',
			'is_valid_css_selector'          => '7ca08f7e88578ae220a227b85123d20437a901447531c940a42336324cbe3d59',
			'selector_targets_pseudo_element'=> '0b2d2ea5212649e7d3e8a46c8e1c0727e774bdbe353899302f953c8b2db6fb75',
			'split_top_level_selector_list'  => 'fff7ef51dc36fbbecf6a211e017a3350da4292673a39b72fe8823d2f046980b2',
		),
		'gravityview' => array(
			'__clone'                             => '9953918e2110cad90f52a4797ffee99372b03d9fcfe0978b7d48d877b5ce122c',
			'__construct'                         => 'a68b515b4e01d7193777b9f2e36a0f9dda02876304fa218bb5e0059bb93e5901',
			'__wakeup'                            => '91de78b3904077379ffbc56c59140445272a1bfe545d38aba7d45b77cd28bff6',
			'apply_exclusion_boundary'             => '3a260db6325439ac305a32851e133bb095288708f80924fe2fdcc70af26aed5c',
			'enqueue_editor_typography'             => '43227c3ff8b95a8ae179e17684d30c6be301f0700a9fd1eb561469a40a7c53f7',
			'get_editor_css'                       => 'addec93a936b768c69c59f8682d507fe44cfc3a83c9e09a70884c4f5408ea593',
			'get_instance'                         => 'e31da18519b543f7cfd47e26ba1f8c9dc1157f37f780a699acc11570bfb411ca',
			'get_negative_scope_selectors'         => '2d6c94a1026f42772775c0856131639ebf55c7dfea02d3244bf8da580bb55532',
			'has_view_block_editor_style_capability'=> '60b53ea2621b90cadb3cfc79b2cd1ab72fb87d07193a3c30185ab898d185e3d7',
			'is_enabled'                           => 'b984cbf7e7030a6ab5fe4766fdb0e2516afc727493ab6970758cef09dc1c6dbd',
			'is_gravityview_runtime_available'     => 'a42475ba4da0385bd61745cf7878384a7023cf1766772dffe7a26f218321dba2',
			'is_valid_css_selector'                => '7ca08f7e88578ae220a227b85123d20437a901447531c940a42336324cbe3d59',
			'selector_targets_pseudo_element'      => '0b2d2ea5212649e7d3e8a46c8e1c0727e774bdbe353899302f953c8b2db6fb75',
			'split_top_level_selector_list'        => 'fff7ef51dc36fbbecf6a211e017a3350da4292673a39b72fe8823d2f046980b2',
		),
	),
);

$evidence_only_versions = array( '3.1.1.1', '3.1.0', '3.3.4', '2.3.16', '1.1.21', '1.5.13' );

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

/** @return array<string,string>|null */
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

/** @return array<string,string>|null */
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

/** @return string[] */
function vf_full_method_lock_violations( array $sources, array $baseline, array $integration_classes ): array {
	$violations = array();

	$bootstrap_methods = vf_extract_class_method_bodies( $sources['bootstrap'], 'VazirFontPlugin' );
	if ( null === $bootstrap_methods || ! isset( $bootstrap_methods['init'] ) ) {
		$violations[] = 'VazirFontPlugin::init() is not discoverable';
	} else {
		$actual_init = hash( 'sha256', vf_normalized_executable_tokens( $bootstrap_methods['init'] ) );
		if ( $baseline['bootstrap_init'] !== $actual_init ) {
			$violations[] = 'VazirFontPlugin::init() executable body changed outside the version-neutrality baseline';
		}
	}

	foreach ( $integration_classes as $label => $class_name ) {
		$actual = vf_class_method_lock( $sources[ $label ], $class_name );
		if ( null === $actual ) {
			$violations[] = $class_name . ' methods are not discoverable';
			continue;
		}
		$expected = $baseline['integrations'][ $label ];
		$actual_names   = array_keys( $actual );
		$expected_names = array_keys( $expected );
		if ( $actual_names !== $expected_names ) {
			$added   = array_values( array_diff( $actual_names, $expected_names ) );
			$removed = array_values( array_diff( $expected_names, $actual_names ) );
			$violations[] = $class_name . ' method membership changed; added=[' . implode( ',', $added ) . '] removed=[' . implode( ',', $removed ) . ']';
		}
		foreach ( $expected as $method_name => $expected_hash ) {
			if ( ! isset( $actual[ $method_name ] ) ) {
				continue;
			}
			if ( $expected_hash !== $actual[ $method_name ] ) {
				$violations[] = $class_name . '::' . $method_name . '() executable body changed outside the version-neutrality baseline';
			}
		}
	}

	return $violations;
}

/** @return string[] Secondary diagnostics only; not the primary conformance proof. */
function vf_secondary_vendor_identity_violations( array $sources, array $evidence_only_versions ): array {
	$violations = array();
	foreach ( $sources as $label => $source ) {
		$normalized = vf_normalized_executable_tokens( $source );
		foreach ( $evidence_only_versions as $version ) {
			if ( false !== strpos( $normalized, $version ) ) {
				$violations[] = $label . ' production executable contains evidence-only version literal ' . $version;
			}
		}

		foreach ( token_get_all( $source ) as $token ) {
			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}
			$name = $token[1];
			if ( 'VAZIR_FONT_VERSION' === $name || 'VAZIR_FONT_SCHEMA_VERSION' === $name ) {
				continue;
			}
			if ( 1 === preg_match( '/(?:version|build[_-]?id)/i', $name ) ) {
				$violations[] = $label . ' production executable references vendor-identity-shaped token ' . $name;
			}
		}
	}
	return array_values( array_unique( $violations ) );
}

/** @return string[] */
function vf_gravity_version_neutrality_violations( array $sources, array $baseline, array $integration_classes, array $evidence_only_versions ): array {
	return array_merge(
		vf_full_method_lock_violations( $sources, $baseline, $integration_classes ),
		vf_secondary_vendor_identity_violations( $sources, $evidence_only_versions )
	);
}

function vf_replace_once( string $source, string $search, string $replace, string $label ): string {
	$count = substr_count( $source, $search );
	vf_version_neutral_assert( 1 === $count, $label . ' mutation anchor is present exactly once' );
	return str_replace( $search, $replace, $source );
}

function vf_primary_lock_rejects( array $mutated_sources, array $baseline, array $integration_classes, string $label ): void {
	$violations = vf_full_method_lock_violations( $mutated_sources, $baseline, $integration_classes );
	if ( array() !== $violations ) {
		fwrite( STDOUT, 'CONTROL: ' . $label . ' rejected with: ' . implode( ' | ', $violations ) . "\n" );
	}
	vf_version_neutral_assert( array() !== $violations, $label . ' is rejected by the primary full executable-method lock' );
}

$sources = array();
foreach ( $production_files as $label => $path ) {
	vf_version_neutral_assert( is_readable( $path ), $label . ' production source is readable' );
	$sources[ $label ] = (string) file_get_contents( $path );
}

$current_violations = vf_gravity_version_neutrality_violations(
	$sources,
	$full_method_lock_baseline,
	$integration_classes,
	$evidence_only_versions
);
vf_version_neutral_assert( array() === $current_violations, 'current Gravity production admission/application code matches the full executable-method lock and secondary diagnostics' );

/* Assignment/bitwise bypass: caller condition remains unchanged. */
$assignment_mutation = $sources;
$assignment_mutation['gravityforms'] = vf_replace_once(
	$assignment_mutation['gravityforms'],
	"\t\t\$this->gf_available = \$this->is_gravity_forms_active();",
	"\t\t\$this->gf_available = (bool) (\n\t\t\t\$this->is_gravity_forms_active()\n\t\t\t& ( defined( 'GF_BUILD_ID' ) && GF_BUILD_ID >= 99715 )\n\t\t);",
	'assignment/bitwise bypass'
);
vf_version_neutral_assert(
	false !== strpos( $assignment_mutation['gravityforms'], 'if ( $this->gf_available )' ),
	'assignment/bitwise mutation retains the existing gf_available caller condition'
);
vf_primary_lock_rejects( $assignment_mutation, $full_method_lock_baseline, $integration_classes, 'assignment/bitwise GF_BUILD_ID bypass' );

/* Existing helper body bypass: caller remains unchanged. */
$helper_body_mutation = $sources;
$helper_body_mutation['gravityflow'] = vf_replace_once(
	$helper_body_mutation['gravityflow'],
	"\t\treturn class_exists( 'Gravity_Flow' );",
	"\t\treturn class_exists( 'Gravity_Flow' ) && ( ! defined( 'GRAVITY_FLOW_BUILD_ID' ) || GRAVITY_FLOW_BUILD_ID >= 99716 );",
	'existing-helper-body bypass'
);
vf_primary_lock_rejects( $helper_body_mutation, $full_method_lock_baseline, $integration_classes, 'existing Gravity Flow helper-body build gate' );

/* New helper bypass: membership changes and an existing reachable method routes through it. */
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
vf_primary_lock_rejects( $new_helper_mutation, $full_method_lock_baseline, $integration_classes, 'new GravityView helper build gate' );

/* Preserve the three earlier falsification controls. */
$gf_version_mutation = $sources;
$gf_version_mutation['bootstrap'] = vf_replace_once(
	$gf_version_mutation['bootstrap'],
	"\t\tif ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) ) {",
	"\t\tif ( class_exists( 'GFForms' ) && class_exists( 'VazirFont_GravityForms_Integration' ) && defined( 'GF_VERSION' ) && GF_VERSION >= '99.7.13' ) {",
	'GF_VERSION bootstrap bypass'
);
vf_primary_lock_rejects( $gf_version_mutation, $full_method_lock_baseline, $integration_classes, 'new/non-evidence GF_VERSION bootstrap gate' );

$strcmp_mutation = $sources;
$strcmp_mutation['bootstrap'] = vf_replace_once(
	$strcmp_mutation['bootstrap'],
	"\t\tif ( class_exists( 'GravityPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' ) ) {",
	"\t\tif ( class_exists( 'GravityPerks' ) && class_exists( 'VazirFont_GravityPerks_Integration' ) && strcmp( (string) GravityPerks::\$version, '99.7.14' ) >= 0 ) {",
	'strcmp bootstrap bypass'
);
vf_primary_lock_rejects( $strcmp_mutation, $full_method_lock_baseline, $integration_classes, 'Gravity Perks strcmp vendor-version gate' );

$build_id_mutation = $sources;
$build_id_mutation['gravityflow'] = vf_replace_once(
	$build_id_mutation['gravityflow'],
	"\t\tif ( ! wp_style_is( \$dependency, 'registered' ) ) {",
	"\t\tif ( defined( 'GRAVITY_FLOW_BUILD_ID' ) && GRAVITY_FLOW_BUILD_ID < 99715 ) {\n\t\t\treturn;\n\t\t}\n\n\t\tif ( ! wp_style_is( \$dependency, 'registered' ) ) {",
	'GRAVITY_FLOW_BUILD_ID adapter bypass'
);
vf_primary_lock_rejects( $build_id_mutation, $full_method_lock_baseline, $integration_classes, 'Gravity Flow GRAVITY_FLOW_BUILD_ID adapter gate' );

/* Vazir's own persisted-schema migration is legitimate and intentionally outside Gravity admission. */
vf_version_neutral_assert(
	false !== strpos( $sources['bootstrap'], "version_compare( \$current_db_version, VAZIR_FONT_SCHEMA_VERSION, '<' )" ),
	"Vazir's persisted-schema VAZIR_FONT_SCHEMA_VERSION/version_compare migration remains present and accepted"
);

/* Exact vendor versions are evidence identity and remain permitted outside production admission. */
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

fwrite( STDOUT, "ALL GRAVITY VERSION-NEUTRALITY FULL-METHOD LOCK CHECKS PASSED\n" );
