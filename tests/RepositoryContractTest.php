<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RepositoryContractTest extends TestCase {
	public function test_supported_font_assets_match_pinned_vazirmatn_release(): void {
		$expected = [
			'300' => 'a3aa104f9a256734ca6769e017b4a2697c3036221e13758e0995a0cbeea969c4',
			'400' => 'e382101336c6eb32cfb31381c027d02d2e0354bad08f6a395d4088beb3db3d91',
			'500' => '3333e31188a2b628db8780ca22fd5aad85bc083ccee9beb8d4d52db18cb98d48',
			'700' => '836fae7d42d83faa249bc00e0099592be98a1fa260d22d82f269b6091e585627',
			'900' => 'e65a05523e6c0a434265913805746ebe6ed48af843e6126a936d06f69d7d47ad',
		];

		foreach ( $expected as $weight => $sha256 ) {
			$path = VAZIR_TEST_ROOT . '/assets/fonts/vazirmatn-' . $weight . '.woff2';
			$this->assertFileExists( $path );
			$this->assertSame( $sha256, hash_file( 'sha256', $path ) );
			$this->assertFileDoesNotExist( VAZIR_TEST_ROOT . '/assets/fonts/vazir-' . $weight . '.woff2' );
		}

		$this->assertSame(
			'17e355067c8284f47743a1ee3b1ef7ff684ff0601eda357f9353b10b3016ab31',
			hash_file( 'sha256', VAZIR_TEST_ROOT . '/assets/fonts/OFL.txt' )
		);
		$this->assertSame(
			'b57746a5f7002c0974c76c32af74079ff7ef1aaf8f35495e9409cfa1eb11e1ca',
			hash_file( 'sha256', VAZIR_TEST_ROOT . '/assets/fonts/AUTHORS.txt' )
		);
		$this->assertFileExists( VAZIR_TEST_ROOT . '/assets/fonts/Vazirmatn-PROVENANCE.md' );
	}

	public function test_vazirmatn_family_is_canonical_while_public_filter_is_preserved(): void {
		$loader = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-loader.php' );
		$gravity = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );
		$this->assertIsString( $loader );
		$this->assertIsString( $gravity );
		$this->assertStringContainsString( "font-family: 'Vazirmatn'", $loader );
		$this->assertStringContainsString( "'Vazirmatn', system-ui", $loader );
		$this->assertStringContainsString( "'Vazirmatn', system-ui", $gravity );
		$this->assertStringContainsString( "'vazir_font_family'", $loader );
		$this->assertStringContainsString( "'vazir_font_family'", $gravity );
	}

	public function test_loader_references_only_woff2_font_sources(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-loader.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( "format('woff2')", $source );
		$this->assertStringNotContainsString( "format('woff')", $source );
		$this->assertStringNotContainsString( "format('truetype')", $source );
	}

	public function test_editor_content_uses_current_wordpress_asset_hook(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-loader.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( "add_action( 'enqueue_block_assets'", $source );
		$this->assertStringNotContainsString( "add_action( 'enqueue_block_editor_assets'", $source );
	}

	public function test_exclusions_are_negative_scope_not_font_resets(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-loader.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'apply_exclusion_boundary', $source );
		$this->assertStringContainsString( "':not(:where('", $source );
		$this->assertStringContainsString( 'selector_targets_pseudo_element', $source );
		$this->assertStringNotContainsString( '$rules .= $candidate . " {\\n\\tfont-family: inherit;\\n}\\n";', $source );
		$this->assertStringContainsString( '$text_selector . " {\\n\\tfont-family: {$family} !important;\\n}\\n";', $source );
	}

	public function test_unowned_gravity_forms_cache_and_cron_operations_are_absent(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-loader.php' );
		$bootstrap = file_get_contents( VAZIR_TEST_ROOT . '/vazir-font-wp.php' );
		$this->assertIsString( $source );
		$this->assertIsString( $bootstrap );
		$this->assertStringNotContainsString( 'GFCache::flush', $source );
		$this->assertStringNotContainsString( 'wp_delete_file', $source );
		$this->assertStringNotContainsString( "delete_transient( 'gforms_css_version'", $source );
		$this->assertStringNotContainsString( 'wp_schedule_event', $bootstrap );
	}

	public function test_gravity_forms_preview_and_no_conflict_use_style_handles(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'gform_preview_styles', $source );
		$this->assertStringContainsString( 'gform_noconflict_styles', $source );
		$this->assertStringContainsString( 'STYLE_HANDLE', $source );
		$this->assertStringNotContainsString( "add_action( 'gform_post_render'", $source );
	}

	public function test_gravity_forms_availability_uses_loaded_required_classes(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );
		$fixture = file_get_contents( VAZIR_TEST_ROOT . '/tests/runtime-contract.php' );
		$this->assertIsString( $source );
		$this->assertIsString( $fixture );
		$this->assertStringContainsString( "class_exists( 'GFForms' ) && class_exists( 'GFCommon' )", $source );
		$this->assertStringNotContainsString( "method_exists( 'GFCommon', 'get_version' )", $source );
		$this->assertStringContainsString( 'class GFCommon {}', $fixture );
		$this->assertStringContainsString( "public static string \$version = '3.1.1.1'", $fixture );
		$this->assertStringNotContainsString( 'public static function get_version', $fixture );
	}

	public function test_gravity_forms_integration_stays_inactive_without_required_runtime_classes(): void {
		$this->assertFalse( class_exists( 'GFForms', false ) );
		$this->assertFalse( class_exists( 'GFCommon', false ) );

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}
		require_once VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php';

		$integration = VazirFont_GravityForms_Integration::get_instance();
		$reflection = new ReflectionClass( $integration );
		$available = $reflection->getProperty( 'gf_available' );
		$available->setAccessible( true );

		$this->assertFalse( $available->getValue( $integration ) );
	}

	public function test_gravity_forms_font_enforcement_consumes_exclusion_authority(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( "\$options['exclude_selectors'] ?? []", $source );
		$this->assertStringContainsString( 'get_negative_scope_selectors', $source );
		$this->assertStringContainsString( 'apply_exclusion_boundary', $source );
		$this->assertStringContainsString( ':not(:where(', $source );
		$this->assertStringContainsString( ':not(:has(:where(', $source );
		$this->assertStringContainsString( '--gf-font-family-base', $source );
		$this->assertStringContainsString( "[] !== \$this->get_negative_scope_selectors()", $source );
		$this->assertStringNotContainsString( 'querySelector', $source );
		$this->assertStringNotContainsString( 'DOMDocument', $source );
	}

	public function test_compatibility_hooks_are_still_present_pending_visual_characterization(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'gform_field_content', $source );
		$this->assertStringContainsString( 'gform_field_css_class', $source );
	}
}
