<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RepositoryContractTest extends TestCase {
	public function test_supported_font_assets_exist(): void {
		foreach ( [ '300', '400', '500', '700', '900' ] as $weight ) {
			$this->assertFileExists( VAZIR_TEST_ROOT . '/assets/fonts/vazir-' . $weight . '.woff2' );
		}
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

	public function test_compatibility_hooks_are_still_present_pending_visual_characterization(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'gform_field_content', $source );
		$this->assertStringContainsString( 'gform_field_css_class', $source );
	}
}
