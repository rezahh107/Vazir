# AGENTS.md — Vazir Font for WordPress

This document defines repository-specific contribution rules for human contributors and autonomous agents. Keep it synchronized with the runtime, CI, and release documentation.

## 1. Repository Snapshot

- **Plugin slug:** `vazir-font-wp`
- **Primary entrypoint:** `vazir-font-wp.php`
- **Current plugin version:** `1.3.0`
- **Minimum WordPress:** `6.7`
- **Minimum PHP:** `7.4`
- **Text domain:** `vazir-font-wp`
- **Domain Path:** `/languages`
- **Class prefix:** `VazirFont_`
- **Autoloader:** bounded SPL autoloader in `vazir-font-wp.php`
- **Persisted option:** `vazir_font_options`
- **Optional integration:** Gravity Forms

When changing version metadata, update the plugin header and `VAZIR_FONT_VERSION` in `vazir-font-wp.php` together.

## 2. Runtime Architecture

The plugin owns typography only. Preserve these boundaries:

- WordPress frontend, wp-admin, login, Block Editor, and Site Editor typography is handled by `VazirFont_Loader`.
- Editor content uses the current `enqueue_block_assets` path.
- Gravity Forms compatibility is handled by `VazirFont_GravityForms_Integration` through WordPress/Gravity Forms style hooks and registered handles.
- Gravity Flow and GravityView evidence profiles characterize coexistence only; they do not create dedicated production integration layers.
- Do not introduce a second settings authority, JavaScript DOM typography engine, or Gravity Forms/Flow/View cache/file ownership.
- Do not flush third-party caches, delete generated files/transients, or add periodic third-party cleanup jobs.

## 3. Font Delivery Invariants

Bundled font assets are static Vazir WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`.

Required behavior:

- generate `@font-face` sources only for packaged `.woff2` files;
- retain `font-display: swap`;
- do not add WOFF/TTF fallbacks unless matching packaged binaries are intentionally introduced and characterized;
- do not add default preload behavior without measured justification;
- keep the public `Vazir` family identity and `vazir_font_family` filter unless a separately characterized migration changes them;
- keep `assets/fonts/OFL.txt` with bundled font files.

A Vazirmatn migration is a separate typography migration, not a cleanup side effect.

## 4. Exclusion Semantics

`exclude_selectors` is the single exclusion authority.

Element-level exclusions are negative applicability boundaries: Vazir `font-family` enforcement must not target an excluded root or its descendants. Do not implement generic exclusions by emitting competing `font-family: inherit`, `initial`, `revert`, or `revert-layer` rules.

Pseudo-element exclusions must not be forced into relational element guards. Dedicated icon-family protections remain responsible for Dashicons and equivalent icon contexts.

The Gravity Forms adapter must consume the same `vazir_font_options['exclude_selectors']` authority. Do not introduce a second selector model or a PHP/DOM imitation of arbitrary CSS selector matching.

## 5. Gravity Forms Compatibility

Preserve the registered-style-handle architecture around:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and scoped Gravity Forms `!important` compatibility rules remain provisional compatibility mechanisms until licensed real-Gravity-Forms browser characterization proves they can be narrowed or removed safely.

Repository stubs and unlicensed CI do **not** count as licensed Gravity Forms runtime proof. If licensed characterization is unavailable, report it as unavailable rather than PASS.

## 6. Directory Expectations

| Path | Purpose |
| --- | --- |
| `vazir-font-wp.php` | Plugin bootstrap, constants, options, autoloading |
| `includes/class-vazirfont-loader.php` | WordPress typography loading and generated CSS |
| `includes/class-vazirfont-admin-settings.php` | Admin settings and validation |
| `includes/class-vazirfont-gravityforms-integration.php` | Optional Gravity Forms compatibility adapter |
| `assets/fonts/` | Packaged Vazir WOFF2 binaries and OFL license |
| `assets/css/` | Shared/static CSS assets |
| `assets/js/` | Admin-side JavaScript |
| `languages/` | Translation template/resources |
| `tests/gravityforms-evidence-lab/` | Deep Gravity Forms profile retained from the first PR #12 batch |
| `tests/product-evidence-lab/` | Shared licensed package/runtime core plus Gravity Flow, GravityView, and combined-stack profiles |
| `docs/CHARACTERIZATION.md` | Evidence boundaries and characterization status |
| `RELEASE.md` | Release verification checklist |

## 7. Local Tooling

Install development dependencies:

```bash
composer install
```

Run the repository checks:

```bash
composer test
composer lint
composer compat
```

`composer test` runs the standalone runtime contract and PHPUnit repository contracts. `composer lint` uses the repository PHPCS ruleset. `composer compat` checks the production PHP surfaces against the configured PHP compatibility range.

## 8. Coding Standards

The authoritative PHPCS configuration is `.phpcs.xml.dist`.

- Follow `WordPress-Core` plus `PHPCompatibilityWP` as configured there.
- Runtime PHP must remain compatible with PHP `7.4+` unless project requirements are deliberately changed.
- Escape rendered output and sanitize persisted/admin input with appropriate WordPress APIs.
- Require capabilities and nonces for state-changing admin operations.
- Prefer bounded, dependency-free changes over new runtime libraries.
- Keep production changes scoped; avoid unrelated refactors during defect repair.

## 9. CI Expectations

GitHub Actions runs on both `push` and `pull_request`.

Current CI coverage includes:

- PHP `7.4`, `8.3`, `8.4`, and `8.5`: syntax checks, `tests/runtime-contract.php`, PHPUnit;
- standards: `composer lint` and `composer compat`;
- WordPress smoke: `6.7/PHP 7.4`, `7.1/PHP 8.3`, `7.1/PHP 8.5`;
- Chromium computed-style characterization on WordPress `7.1` with Twenty Twenty-One and Twenty Twenty-Five;
- a separately diagnosable Product-Wide Reproducible Evidence Lab targeting WordPress `7.1`, PHP `8.3`, Chromium, and licensed profiles `gravityforms`, `gravityflow`, `gravityview`, and `gravity-stack`.

The existing generic WordPress browser fixture remains the `wordpress` profile authority and is not duplicated inside the licensed matrix.

The product evidence lab must fail closed unless every required Owner-supplied package matches its exact expected byte size, SHA-256, archive safety rules, entrypoint, plugin identity, and version. Licensed ZIPs must never be committed or uploaded as CI artifacts. A configured profile is not evidence by itself: claims require an actually executed exact-Head run, and unavailable runner/package conditions remain `ENVIRONMENT_UNAVAILABLE`/`NOT_PROVEN`, not PASS.

A PASS belongs only to the profile and scenarios that executed. Gravity Forms PASS does not prove Gravity Flow or GravityView, and combined-stack PASS is representative coexistence evidence rather than exhaustive compatibility.

## 10. Testing Rules for Changes

- Production behavior changes require a deterministic contract test where feasible.
- CSS/typography changes that depend on cascade or computed style require browser characterization, not source inspection alone.
- Changes to Gravity Forms compatibility should preserve Preview/No Conflict registered handles and include deterministic repository/runtime contracts.
- Any claim about real Orbital/Theme Framework, Legacy Markup, Preview, Form Editor, AJAX, multi-page, validation rerender, conditional logic, or Gravity Forms icons requires the licensed `gravityforms` profile.
- Claims about Gravity Flow or GravityView surfaces require their respective licensed profile; fixture-created state proves behavior after that state exists, not production reachability of every setup path.
- Keep exact-Head CI evidence bound to the commit and profile being evaluated.

## 11. Internationalization, Security, and Accessibility

- Wrap user-facing strings with WordPress translation functions using `vazir-font-wp`.
- Regenerate `languages/vazir-font-wp.pot` when translatable strings change.
- Block direct access to include files with the established `ABSPATH` guard.
- Do not add arbitrary filesystem operations or unowned cache cleanup.
- Preserve accessible labels, keyboard behavior, and non-color-only status cues in admin UI.

## 12. Release Rules

Use `RELEASE.md` as the release gate.

At minimum:

- keep version metadata consistent;
- run all repository checks;
- verify packaged font URLs/assets;
- run WordPress computed-style characterization;
- run the relevant licensed product profile for any Gravity Forms/Flow/View compatibility claim made by the release;
- do not promote unavailable, stub-only, or different-profile evidence to PASS;
- build the production artifact without development-only tooling unless explicitly required;
- publish only with Owner authorization.

## 13. Documentation Maintenance

When runtime behavior, supported versions, test matrices, option semantics, or release evidence changes, update the relevant documentation in the same change. Do not leave branch-specific language in long-lived `main` documentation after a change has merged.
