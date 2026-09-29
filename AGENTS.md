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
- **Optional integrations:** Gravity Forms; capability-admitted Gravity Flow typography compatibility

When changing version metadata, update the plugin header and `VAZIR_FONT_VERSION` in `vazir-font-wp.php` together.

## 2. Runtime Architecture

The plugin owns typography only. Preserve these boundaries:

- WordPress frontend, wp-admin, login, Block Editor, and Site Editor typography is handled by `VazirFont_Loader`.
- Editor content uses the current `enqueue_block_assets` path.
- Gravity Forms compatibility is handled by `VazirFont_GravityForms_Integration` through WordPress/Gravity Forms style hooks and registered handles.
- Gravity Flow typography compatibility is handled by `VazirFont_GravityFlow_Integration` through `gravityflow_enqueue_admin_scripts` / `gravityflow_enqueue_frontend_scripts` and host stylesheet dependencies. Gravity Flow continues to own Inbox data/query, assignment, authorization, workflow state, search, pagination, filtering, navigation, AG Grid lifecycle, and icon rendering.
- GravityView remains an evidence profile, not a dedicated production integration layer.
- Do not introduce a second settings authority, JavaScript DOM typography engine, or Gravity Forms/Flow/View cache/file ownership.
- Do not flush third-party caches, delete generated files/transients, or add periodic third-party cleanup jobs.

## 3. Font Delivery Invariants

Bundled font assets are pinned static Vazirmatn `v33.003` WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`.

Required behavior:

- generate `@font-face` sources only for packaged `.woff2` files;
- retain `font-display: swap`;
- do not add WOFF/TTF fallbacks unless matching packaged binaries are intentionally introduced and characterized;
- do not add default preload behavior without measured justification;
- use the truthful canonical `Vazirmatn` CSS family for the bundled upstream font;
- retain the public `vazir_font_family` filter as the compatibility API for overriding the complete stack;
- do not create a hidden legacy `Vazir` alias for Vazirmatn bytes;
- compatibility adapters must not duplicate Loader-owned `@font-face` delivery when the context Loader already provides it;
- keep exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt` with the bundled files;
- keep `assets/fonts/Vazirmatn-PROVENANCE.md` synchronized with the pinned release archive and bundled SHA-256 digests.

## 4. Exclusion Semantics

`exclude_selectors` is the single exclusion authority.

Element-level exclusions are negative applicability boundaries: Vazirmatn `font-family` enforcement must not target an excluded root or its descendants. Do not implement generic exclusions by emitting competing `font-family: inherit`, `initial`, `revert`, or `revert-layer` rules.

Pseudo-element exclusions must not be forced into relational element guards. Dedicated icon-family protections remain responsible for Dashicons and equivalent icon contexts.

Gravity compatibility adapters must consume the same `vazir_font_options['exclude_selectors']` authority. Do not introduce a second incompatible selector model or a PHP/DOM imitation of arbitrary CSS selector matching. Inheritable Gravity Forms/Flow rules must fail closed when an exclusion cannot be represented safely.

## 5. Gravity Compatibility

### Gravity Forms

Preserve the registered-style-handle architecture around:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and scoped Gravity Forms `!important` compatibility rules remain provisional compatibility mechanisms until licensed real-Gravity-Forms browser characterization proves they can be narrowed or removed safely.

Exact Gravity Forms `3.1.1.1` browser characterization is the current authority for bounded direct-font corrections added beyond the existing Theme Framework/current/Legacy form rules. The machine-readable admission record is `tests/gravityforms-evidence-lab/admitted-selector-evidence.json`; it is bound to evidence-only Head `555a956849139cac48c646894bef83dd44191a2c` and workflow run `36588232341`. Preserve these boundaries:

- Legacy multipage correction is limited to `.gform_legacy_markup_wrapper .gf_step_number` and `.gform_legacy_markup_wrapper .gf_progressbar_percentage`. `.gf_step_label` and `.gf_progressbar_title` already resolved to Vazirmatn before the repair and must not be added as direct production selectors without new exact-runtime evidence;
- admin-component correction is limited to `.gform-admin .gform-dropdown`, `.gform-admin .gform-dropdown__control-text`, and `.gform-admin .gform-button`. `.gform-admin .gform-dropdown__group-text` was not deterministically rendered and remains `NOT_PROVEN`; do not infer admission from the component family;
- Preview chrome correction is limited to the runtime-proven `#preview_hdr` and `#preview_note` nodes; do not treat the Preview body or every Preview descendant as owned typography;
- all such selectors must continue through the existing `exclude_selectors` negative-applicability machinery rather than a second selector language;
- do not target Gravity Forms icon pseudo-elements or replace `gform-icons-orbital`, `gform-icons-admin`, `gform-icons-common`, `gravity-components-icons`, Dashicons, or any other host glyph family;
- source-only risk, an unrendered admin component family, or a selector found in vendor CSS is not authority to add production repair. Require exact-runtime computed-style evidence first;
- repository contracts must continue to derive admitted selectors from the pre-repair manifest and reject reintroduction of selectors classified `ALREADY_VAZIRMATN` or `NOT_PROVEN`.

### Gravity Flow

The dedicated adapter uses capability-based runtime admission. Preserve these invariants:

- runtime admission is not version-gated and must not use an accepted-version whitelist;
- initialize when the Gravity Flow runtime is present, then attach only through the product's supported admin/frontend enqueue actions;
- at the actual enqueue boundary, require the corresponding host stylesheet handle (`gravityflow_admin_css` / `gravityflow_theme_css`) to be registered; if it is unavailable, fail closed for that surface instead of emitting broad fallback CSS;
- depend on those host styles rather than editing host assets;
- repair only material text surfaces that explicitly defeat normal inheritance: the AG Grid theme root, AG text/date inputs, and the Flow-bound Flatpickr popup;
- allow selector drift to become a natural no-op when a later runtime stops rendering a known selector; do not compensate with broad selectors merely to force coverage;
- do not add JS or DOM mutation for typography;
- do not overwrite `agGridAlpine`, `gflow-icons-common`, Dashicons, Gravity Forms icon families, or other host glyph families;
- gate Flow repair through the existing Gravity compatibility option plus the corresponding frontend/admin context option; do not introduce a new stored settings schema for Flow alone.

Runtime admission and compatibility evidence are separate. Real browser/runtime qualification currently covers the exact Gravity Flow `3.1.0` package used by the licensed evidence lab. A synthetic alternate-version runtime contract may prove that version identity does not control production admission, but it must never be represented as real compatibility evidence for that version.

Repository stubs and unlicensed CI do **not** count as licensed Gravity Forms/Flow runtime proof. If licensed characterization is unavailable, report it as unavailable rather than PASS.

## 6. Directory Expectations

| Path | Purpose |
| --- | --- |
| `vazir-font-wp.php` | Plugin bootstrap, constants, options, autoloading |
| `includes/class-vazirfont-loader.php` | WordPress typography loading and generated CSS |
| `includes/class-vazirfont-admin-settings.php` | Admin settings and validation |
| `includes/class-vazirfont-gravityforms-integration.php` | Optional Gravity Forms compatibility adapter |
| `includes/class-vazirfont-gravityflow-integration.php` | Capability-bounded Gravity Flow typography adapter |
| `assets/fonts/` | Pinned Vazirmatn WOFF2 binaries, upstream license/authors, and provenance |
| `assets/css/` | Shared/static CSS assets |
| `assets/js/` | Admin-side JavaScript |
| `languages/` | Translation template/resources |
| `tests/gravityflow-runtime-contract.php` | Deterministic Gravity Flow admission/adapter contract |
| `tests/gravityforms-evidence-lab/` | Deep Gravity Forms exact-runtime/browser profile and selector-admission manifest |
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

`composer test` runs the standalone core/Gravity Forms contract, the Gravity Flow adapter contract for both the currently qualified `3.1.0` identity and a synthetic alternate version identity, and PHPUnit repository contracts. The synthetic alternate identity proves only that admission is not version-gated. `composer lint` uses the repository PHPCS ruleset. `composer compat` checks the production PHP surfaces against the configured PHP compatibility range.

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

- PHP `7.4`, `8.3`, `8.4`, and `8.5`: syntax checks, standalone contracts, PHPUnit;
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
- Any claim about real Orbital/Theme Framework, Legacy Markup, Preview, Form Editor, AJAX, multi-page, validation rerender, conditional logic, admin components, or Gravity Forms icons requires the licensed `gravityforms` profile.
- A source declaration in `admin-components.min.css`, `preview.css`, or Legacy CSS is only risk evidence. Production repair requires a rendered exact-runtime failure on the actual text-bearing node; already-correct components must not receive speculative broad fixes.
- Gravity Flow admission changes require deterministic coverage separating version identity from actual host capabilities. Real Gravity Flow typography claims still require the licensed `gravityflow` profile to measure the actual rendered inner AG Grid/Flatpickr component, not only `.gflow-inbox`. Wrapper PASS must not be promoted to proof of inner AG Grid typography.
- Claims about GravityView surfaces require the licensed `gravityview` profile; fixture-created state proves behavior after that state exists, not production reachability of every setup path.
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
- do not promote unavailable, stub-only, wrapper-only, synthetic-version, or different-profile evidence to PASS;
- build the production artifact without development-only tooling unless explicitly required;
- publish only with Owner authorization.

## 13. Documentation Maintenance

When runtime behavior, supported versions, test matrices, option semantics, or release evidence changes, update the relevant documentation in the same change. Do not leave branch-specific language in long-lived `main` documentation after a change has merged.
