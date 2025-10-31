# AGENTS.md — Vazir Font for WordPress (v1.0)

Welcome! This document encodes the repository rules for both human contributors and autonomous agents. Follow every instruction in this file when you touch any file in this project.

## 1. Repository Snapshot
- **Plugin slug:** `vazir-font-wp`
- **Primary entrypoint:** `vazir-font-wp.php`
- **PHP namespace/prefix:** `VazirFont_`
- **Current plugin version:** `1.1.0` (update header + `VAZIR_FONT_VERSION` together)
- **Text domain:** `vazir-font-wp`
- **Domain Path:** `/languages`
- **Assets path:** `assets/`
- **Autoloader:** SPL autoloader with `VazirFont_` prefix registered in the main plugin file.

## 2. Directory Expectations
| Path | Purpose | Notes |
| ---- | ------- | ----- |
| `includes/` | PHP classes (`class-*.php`) | Guard every file with `defined( 'ABSPATH' ) || exit;`.
| `assets/css/` | Shared stylesheets | Keep `vazir-fonts.css` generic; scope admin-only rules to `admin.css`.
| `assets/js/` | Admin scripts | Wrap logic in IIFE; enqueue via `VazirFont_Admin_Settings::enqueueAdminAssets()` only where needed.
| `assets/fonts/` | Bundled Vazir font binaries | Ship the SIL OFL license file when adding fonts.
| `languages/` | Translation sources (`.pot`, `.po`, `.mo`) | Create if missing before shipping translations.

## 3. Local Environment & Tooling
1. PHP ≥ 7.4, WordPress ≥ 5.8 for runtime testing.
2. Install dev tools via Composer:
   ```bash
   composer install
   ```
3. Run linting with WordPress Coding Standards and PHPCompatibility:
   ```bash
   vendor/bin/phpcs --standard=WordPress --extensions=php,inc .
   ```
   > For larger diffs add a project-specific `phpcs.xml.dist`; keep `vendor/` and `node_modules/` excluded.
4. JavaScript/CSS linting is manual—keep changes small and document deviations in PRs.
5. Optional: generate translations with `wp i18n make-pot` (WP-CLI) targeting `languages/vazir-font-wp.pot`.

## 4. Coding Standards
### PHP
- Follow **PSR-12** formatting plus **WordPress Core/Docs/Extra** rules.
- Use tabs for indentation, spaces for alignment.
- Escape all output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()` as appropriate).
- Sanitize all option input via `sanitize_text_field`, `absint`, etc., before persisting.
- Always check capabilities (`current_user_can( 'manage_options' )`) before rendering admin screens or mutating settings.
- Prefer dependency-free solutions over new libraries unless justified.
- Maintain Yoda conditions when comparing with literals.

### JavaScript
- Keep code ES5-compatible (for WordPress admin). No transpilation steps exist.
- Wrap admin scripts in `(function( $ ) { ... })( jQuery );` and enable strict mode.
- Provide translation-ready strings via `wp_localize_script()` before using them in JS (no hard-coded Persian strings).

### CSS
- Use BEM-ish utility classes prefixed with `.vazir-font-` for plugin-specific styling.
- Place reusable variables in `:root` and prefer logical properties for RTL friendliness.

## 5. Security Checklist
- Block direct access at top of every PHP file (except the main plugin bootstrap).
- Never echo unsanitized request data or option values.
- Nonces are required for every state-changing admin action or AJAX endpoint.
- Database access must go through `$wpdb->prepare()` or higher-level APIs.
- Do not introduce arbitrary file operations; rely on WordPress APIs for uploads and filesystem writes.

## 6. Performance Guidelines
- Avoid flushing the global object cache unless absolutely necessary; provide granular hooks instead.
- Load fonts conditionally: respect the user options that toggle frontend, admin, login, and Gravity Forms contexts.
- Keep enqueued assets small; use `.min` variants if you introduce heavy dependencies.
- Any scheduled events must register their schedule (`cron_schedules`) before use.

## 7. Internationalization & RTL
- Wrap every user-facing string with translation functions using the `vazir-font-wp` text domain.
- Update/generate `languages/vazir-font-wp.pot` whenever strings change.
- CSS must respect RTL context via `[dir="rtl"]` selectors or logical properties.
- For JavaScript prompts, source localized strings via `wp_localize_script()` object keys.

## 8. Accessibility
- Provide accessible labels for admin form controls (use `<label>` or `aria-` attributes).
- Avoid color-only signals; ensure sufficient contrast.
- Keyboard interactions in admin screens must remain functional.

## 9. Asset & License Rules
- Fonts are licensed under **SIL Open Font License 1.1**; do not rename the typeface.
- The plugin code is **GPLv2+**. All bundled third-party assets must be GPL-compatible.
- `VazirFont_Loader::generateFontFaces()` currently emits `woff2`, `woff`, and `ttf` sources—ensure matching binaries exist (or update the method) whenever you adjust fonts.

## 10. Testing Expectations
When touching business logic, provide at least one of:
- Manual verification notes (steps, WP version, browser).
- Automated tests (PHPUnit or integration) if feasible.
- Screenshots for UI-affecting changes (attach via PR description or artifacts).

All tests must pass with `WP_DEBUG` enabled.

## 11. Release Checklist
1. Bump version in `vazir-font-wp.php` header and `VAZIR_FONT_VERSION` constant.
2. Update `README.md` and `CHANGELOG.md` (if present) with the new version details.
3. Regenerate the translation template: `wp i18n make-pot . languages/vazir-font-wp.pot`.
4. Confirm `assets/fonts/` contains all required binaries plus the `OFL.txt` license.
5. Run smoke tests with `WP_DEBUG = true` enabled.
6. Tag the release using semantic versioning (major.minor.patch).

## 12. Git & PR Guidance
- Keep commits scoped and well-described (English preferred for commit messages).
- When modifying multiple areas (PHP, assets, docs) split into logical commits if possible.
- Reference related issues or tickets in commit bodies.
- Every PR must summarize changes, testing evidence, and potential impacts.

## 13. File-Specific Notes
- `includes/class-gravity-forms-integration.php` is currently a placeholder. If you implement functionality, ensure Gravity Forms is loaded (`class_exists( 'GFForms' )`) before hooking and cover sanitization/escaping.
- `assets/css/admin.css` is intentionally empty; populate only with admin-specific styles.
- Avoid altering font binary filenames—they map directly to enqueue logic.

## 14. Font-Specific Standards (ویژه فونت وزیر)

### فونت فیس‌ها و فرمت‌ها
- از فرمت `woff2` به عنوان گزینهٔ پیش‌فرض استفاده کنید و در صورت نیاز فرمت‌های مکمل را همگام با باینری‌های موجود به‌روز نگه دارید.
- تمام تعریف‌های `@font-face` باید شامل `font-display: swap` باشند تا از بروز FOIT جلوگیری شود.
- مقدار `font-family` را به‌صورت انگلیسی و ثابت با عنوان `"Vazir"` نگه دارید.

### مدیریت وزن‌های فونت
```php
$font_weights = [ '300', '400', '500', '700', '900' ];
```
- وزن‌های فعال باید با گزینهٔ ذخیره‌شده همگام شوند و از بارگذاری فایل‌های اضافه خودداری شود.
- سلکتورهای مستثنی‌شده را از طریق فیلتر `vazir_font_exclude_selectors` توسعه دهید تا از تداخل با فونت‌آیکن‌ها جلوگیری شود.

### محلی‌سازی هوشمند فونت
- فونت را فقط برای زبان‌های فارسی و عربی فعال کنید؛ به عنوان مثال `is_rtl()` یا بررسی `get_locale()` را در هوک `wp_enqueue_scripts` اعمال کنید.
- در صورت غیرفعال بودن گزینهٔ کاربر یا عدم تطابق زبان، هیچ دارایی فونتی را بارگذاری نکنید.

## 15. Automated Testing Setup

### PHPUnit Configuration
```xml
<!-- phpunit.xml.dist -->
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="vazir-font-plugin">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Test Coverage Requirements
- کلاس‌های هسته‌ای `VazirFont_Loader` و `VazirFont_Admin_Settings` را برای سناریوهای فعال/غیرفعال‌سازی گزینه‌ها پوشش دهید.
- اعتبارسنجی و پاکسازی گزینه‌ها را با داده‌های ورودی معتبر و نامعتبر تست کنید.
- بارگذاری شرطی فونت‌ها را برای فرانت‌اند، ادمین، صفحه ورود و ادغام گرویتی فرمز بررسی کنید.

## 16. Compatibility Notes

### WordPress Multisite
- پلاگین با پرچم `Network: false` منتشر می‌شود و تنظیمات در هر سایت به‌صورت مستقل ذخیره می‌شوند.
- در زمان پاک‌سازی، تنها گزینه‌های سایت فعال را حذف کنید مگر آنکه به‌صراحت برای شبکه پیاده‌سازی شود.

### Caching Plugins
- از اکشن `vazir_font_clear_cache` برای همگام‌سازی با افزونه‌های کش مانند WP Rocket و W3 Total Cache استفاده کنید.
- از فراخوانی مستقیم `wp_cache_flush()` خودداری کنید؛ پاک‌سازی باید محدود به دارایی‌های پلاگین باشد.

### Page Builders
- پلاگین با Elementor، Gutenberg و Classic Editor تست شده است؛ انتظار می‌رود فونت در تمام ادیتورها اعمال شود.
- در صورت بروز ناسازگاری با صفحه‌سازهای ثالث، امکان غیرفعال‌سازی فونت در صفحات خاص را از طریق فیلترها فراهم کنید.

Adhering to this AGENTS.md keeps the plugin compliant with WordPress standards and ensures smooth collaboration. When in doubt, add clarifying comments or extend this file with new rules.
