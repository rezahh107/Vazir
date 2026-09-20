<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminSettingsContractTest extends TestCase {
	public function test_settings_page_keeps_native_settings_api_and_scoped_assets(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-admin-settings.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'register_setting(', $source );
		$this->assertStringContainsString( 'add_settings_section(', $source );
		$this->assertStringContainsString( 'add_settings_field(', $source );
		$this->assertStringContainsString( "settings_fields( 'vazir_font_settings' )", $source );
		$this->assertStringContainsString( 'do_settings_fields( self::PAGE_SLUG', $source );
		$this->assertStringContainsString( 'action="options.php"', $source );
		$this->assertStringContainsString( "'settings_page_' . self::PAGE_SLUG !== \$hook", $source );
		$this->assertStringContainsString( "'label_for'", $source );
	}

	public function test_settings_copy_distinguishes_product_identity_from_vazirmatn_typeface(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-admin-settings.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'این صفحه تنظیمات افزونه «فونت وزیر» است.', $source );
		$this->assertStringContainsString( 'Vazirmatn نسخه 33.003', $source );
		$this->assertStringNotContainsString( 'این افزونه فونت وزیر را به تمام بخش‌های وردپرس شما اضافه می‌کند.', $source );
	}

	public function test_dormant_reset_and_page_global_submit_handler_are_removed(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/assets/js/admin.js' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( '#vazir-font-settings-form', $source );
		$this->assertStringNotContainsString( 'vazir-font-reset', $source );
		$this->assertStringNotContainsString( 'confirmReset', $source );
		$this->assertStringNotContainsString( 'defaultSelectors', $source );
		$this->assertStringNotContainsString( "$( document ).on( 'submit', 'form'", $source );
	}

	public function test_admin_css_is_root_scoped_and_uses_preview_only_font_family(): void {
		$source = file_get_contents( VAZIR_TEST_ROOT . '/assets/css/admin.css' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( '.vazir-font-settings', $source );
		$this->assertStringContainsString( "font-family: 'Vazirmatn Preview'", $source );
		$this->assertStringContainsString( '#vazir-font-exclude_selectors', $source );
		$this->assertStringNotContainsString( 'body {', $source );
		$this->assertStringNotContainsString( '.wp-admin {', $source );
	}
}
