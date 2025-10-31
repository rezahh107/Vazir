# AGENTS.md — Vazir Font for WordPress (v1.1)

Welcome! This document encodes the repository rules for both human contributors and autonomous agents. Follow every instruction in this file when you touch any file in this project.

## 1. Repository Snapshot
- **Plugin slug:** `vazir-font-wp`
- **Primary entrypoint:** `vazir-font-wp.php`
- **PHP namespace/prefix:** `VazirFont_`
- **Current plugin version:** `1.1.0` (keep header + `VAZIR_FONT_VERSION` synchronized)
- **Text domain:** `vazir-font-wp`
- **Domain Path:** `/languages`
- **Assets path:** `assets/`
- **Autoloader:** Anonymous SPL autoloader registered in the bootstrap file with the `VazirFont_` prefix

## 2. Directory Expectations
| Path | Purpose | Notes |
| ---- | ------- | ----- |
| `includes/` | PHP classes (`class-*.php`) | Guard every file with `defined( 'ABSPATH' ) || exit;`. |
| `assets/css/` | Shared stylesheets | Keep `vazir-fonts.css` generic; scope admin-only rules to `admin.css`. |
| `assets/js/` | Admin scripts | Wrap logic in an IIFE and enqueue only through `VazirFont_Admin_Settings::enqueueAdminAssets()`. |
| `assets/fonts/` | Bundled Vazir font binaries | Ship the SIL OFL license file (`OFL.txt`) whenever fonts change. |
| `languages/` | Translation sources (`.pot`, `.po`, `.mo`) | Create the directory before shipping translations. |

## 3. Font-Specific Standards (ویژه فونت وزیر)

### فونت فیس‌ها و فرمت‌ها
```php
@font-face {
    font-family: "Vazir";
    src: url('../fonts/vazir-400.woff2') format('woff2'),
         url('../fonts/vazir-400.woff') format('woff');
    font-display: swap;
    font-weight: 400;
    font-style: normal;
}
```
- Use `woff2` as the primary format; add complementary formats only when the binaries exist.
- Always include `font-display: swap` to avoid FOIT.
- Keep the `font-family` name exactly `"Vazir"`.

### مدیریت وزن‌های فونت
```php
$font_weights = [ '300', '400', '500', '700', '900' ];
```
- Keep loaded weights aligned with saved options to avoid unnecessary asset requests.
- Extend excluded selectors through the `vazir_font_exclude_selectors` filter to prevent conflicts with icon fonts.

### محلی‌سازی هوشمند فونت
- Apply fonts only for Persian and Arabic locales (e.g., via `is_rtl()` or `get_locale()` checks) within enqueue hooks.
- If the user disables a context or the locale is not Persian/Arabic, do not enqueue any font assets.

## 4. Local Environment & Tooling
1. PHP ≥ 7.4 and WordPress ≥ 5.8 for runtime validation.
2. Install development dependencies via Composer:
   ```bash
   composer install
   ```
3. Run linting with WPCS + PHPCompatibility:
   ```bash
   vendor/bin/phpcs --standard=WordPress --extensions=php,inc .
   ```
4. JavaScript and CSS linting is manual—keep changes small and document any deviations.
5. Generate translations when strings change:
   ```bash
   wp i18n make-pot . languages/vazir-font-wp.pot --domain=vazir-font-wp
   ```

## 5. RTL & Persian Language Guidance
### CSS RTL Handling
```css
.vazir-font-element {
    text-align: start;
    padding-inline-start: 1rem;
}

[dir="rtl"] .vazir-font-admin-notice {
    margin-inline-start: 0;
    margin-inline-end: 10px;
}
```
- Prefer logical properties to keep RTL/LTR parity.
- Test admin notices and settings screens with Persian content.

### Persian Text Conventions
- Wrap all user-facing strings with translation helpers using `vazir-font-wp`.
- Save files as UTF-8 without BOM.
- Provide translator comments when strings include placeholders or context.

## 6. Security Checklist
- Block direct access at the top of every PHP file except `vazir-font-wp.php`.
- Sanitize every option and request before persistence; escape output on render.
- Require capability checks (`current_user_can( 'manage_options' )`) for admin mutations.
- Ensure nonces guard every state-changing action or AJAX endpoint.
- Database access must use `$wpdb->prepare()` or higher-level APIs.
- For font file handling, validate file types and sanitize any dynamic CSS emitted.

## 7. Performance Guidelines
- Avoid global cache flushes; expose granular hooks like `vazir_font_clear_cache` instead.
- Respect user toggles for frontend/admin/login/Gravity Forms contexts when enqueuing fonts.
- Keep assets lightweight; prefer minified variants when introducing new dependencies.
- Register custom cron schedules before scheduling them.
- Implement `<link rel="preload">` only for critical weights (e.g., 400, 700).

## 8. Testing Checklist
### Manual
- [ ] Font loads on frontend RTL theme.
- [ ] Admin settings render Persian strings correctly.
- [ ] Gravity Forms integration works when enabled.
- [ ] No PHP notices under `WP_DEBUG = true`.
- [ ] Font weight toggles reflect on frontend output.
- [ ] Cache clearing hook fires on option updates.

### Browser Coverage
- Verify Chrome, Firefox, Safari, and Edge on Windows and macOS.
- Confirm mobile responsiveness with Persian content.

## 9. Release Checklist
1. Bump version in `vazir-font-wp.php` header and the `VAZIR_FONT_VERSION` constant.
2. Update `README.md` and `CHANGELOG.md` (if present).
3. Regenerate translations: `wp i18n make-pot . languages/vazir-font-wp.pot`.
4. Ensure `assets/fonts/` includes required binaries plus `OFL.txt`.
5. Run smoke tests with `WP_DEBUG = true` enabled on WordPress 6.5+ / PHP 8.1.
6. Tag release with semantic versioning.

## 10. Git & PR Guidelines
### Commit Messages
```
feat: add new font weight support
fix: resolve RTL alignment in admin
docs: update installation instructions
perf: optimize font loading sequence
```
- Keep commits focused and well-described (English preferred).
- Split multi-scope changes into logical commits when possible.
- Summarize changes, testing evidence, and impacts in PR descriptions.
- Include screenshots for UI changes and note manual verification steps.

## 11. File-Specific Notes
- `includes/class-loader.php`: handle context-aware font enqueuing and ensure dynamic CSS honors excluded selectors.
- `includes/class-admin-settings.php`: sanitize all user inputs, guard with nonces/capability checks, and localize JS strings.
- `includes/class-gravity-forms-integration.php`: hook only when `class_exists( 'GFForms' )` and sanitize output.
- `VazirFont_Loader::generateFontFaces()`: currently emits `woff2`, `woff`, and `ttf` sources; keep binaries synchronized or drop unused formats and update this document.
- Any structural, licensing, or core-behavior change must be accompanied by an update to `AGENTS.md` reflecting the new rules.

## 12. Emergency Protocols
### Breaking Changes
- Do not remove supported font weights without a major version bump.
- Maintain backward compatibility for stored settings; provide migrations when schema changes.

### Security Issues
- Prioritize fixes for font asset exposure or capability escalation.
- Communicate transparently about compatibility or security regressions.

---

Adhering to this AGENTS.md keeps the plugin compliant with WordPress standards and ensures smooth collaboration. When in doubt, add clarifying comments or extend this file with new rules.

## 13. Automated Testing Setup

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

### Test Coverage Expectations
- Cover core classes (`VazirFont_Loader`, `VazirFont_Admin_Settings`) for option toggles and sanitization paths.
- Validate option sanitization with both valid and invalid payloads.
- Ensure conditional font enqueuing works for frontend, admin, login, and Gravity Forms contexts.
- Add integration smoke tests when adding new subsystems.

## 14. Compatibility Notes

### WordPress Multisite
- The plugin ships with `Network: false`; settings remain per-site by default.
- During uninstall/cleanup, remove only the active site's options unless network support is explicitly implemented.

### Caching Plugins
- Use the `vazir_font_clear_cache` action so integrators can tie into cache purges (WP Rocket, W3 Total Cache, etc.).
- Avoid calling `wp_cache_flush()` directly; stick to plugin-scoped cache invalidation.

### Page Builders
- Tested against Elementor, Gutenberg, and Classic Editor; fonts should apply across editors.
- Provide filters or settings to disable fonts on specific builder pages if compatibility issues arise.
