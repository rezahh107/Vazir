# Vazir Font for WordPress

A self-hosted Persian typography plugin for WordPress with optional Gravity Forms, Gravity Flow, and Gravity Perks compatibility.

## Release 1.4.0

`v1.4.0` is a backward-compatible feature/compatibility release over the public `v1.3.0` release. It keeps the official self-hosted Vazirmatn `v33.003` payload and adds the stable merged compatibility work qualified on these exact licensed runtimes:

- Gravity Flow `3.1.0`: bounded AG Grid and Flow-owned Flatpickr typography repair through supported host enqueue/style capabilities;
- Gravity Forms `3.1.1.1`: closure of independently reproduced Legacy, admin-component, and Preview typography gaps while preserving existing Theme Framework, AJAX/rerender, exclusions, icon ownership, and No Conflict behavior;
- Gravity Perks `2.3.16`: standalone Perk Settings typography repair through WordPress' supported `print_styles_array` boundary while Gravity Perks retains routing, rendering, saving, controls, scripts, notices, and host-style ownership;
- shared Gravity descendant-exclusion hardening through `VazirFont_Selector_Boundary`, including quoted/attribute/functional-selector handling, real `:has()` fail-closed behavior, pseudo-element protection, and no `ctype_*` runtime dependency.

The persisted option/API contract is unchanged. Product version `1.4.0` is intentionally separate from the unchanged persisted option schema version `1.3.0`, so this release bump alone does not run an options migration.

GravityView targeted Gutenberg/editor/date/oEmbed closure work is **not** part of `v1.4.0`. GravityView `3.3.4` remains an existing regression/evidence profile only; new GravityView qualification or production repair belongs to a later release. Compatibility evidence is exact-version-bound even where production admission is capability-based, so this release does not claim unexecuted Gravity product versions.

## Intended runtime coverage

The plugin is designed to cover:

- WordPress frontend in classic and block themes;
- wp-admin;
- the login screen;
- Block Editor and Site Editor content canvases through `enqueue_block_assets`;
- Gravity Forms frontend, Preview, Form Editor, and No Conflict Mode through registered WordPress style handles;
- currently supported Gravity Forms legacy/current wrapper markup;
- Gravity Flow Inbox typography through the product's supported admin/frontend enqueue seams, including the AG Grid text root, material text/date inputs, and Flow-bound Flatpickr calendar without replacing host-owned icon families or Inbox behavior;
- Gravity Perks standalone Perk Settings typography through WordPress' `print_styles_array` boundary while the host `gwp-admin` stylesheet is being processed.

Automated PHP and repository contracts verify the loading/API paths. Real-WordPress smoke lanes verify bootstrap and enqueue behavior, and Chromium computed-style lanes exercise WordPress frontend/login/admin/editor coverage on classic and block themes. Licensed Gravity product coverage is handled separately by the Product-Wide Reproducible Evidence Lab; see `docs/CHARACTERIZATION.md`.

## Font delivery

The bundled typeface is the official upstream **Vazirmatn v33.003** release from `rastikerdar/vazirmatn`, pinned to release commit `83629f877e8f084cc07b47030b5d3a0ff06c76ec`. The plugin self-hosts the static WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`; it does not fetch font bytes at runtime.

`assets/fonts/Vazirmatn-PROVENANCE.md` records the exact upstream release archive identity, source paths, byte sizes, SHA-256 digests, license, and author material used to reproduce the bundled files.

Static delivery remains intentional. The product already exposes five discrete weight selections, while the upstream variable webfont is 111,152 bytes and each selected static face is approximately 50–51 KiB. Static faces preserve the existing settings model and let the browser request only weights actually used by a page. Variable delivery would become advantageous only when enough distinct weights are consumed on the same surface to outweigh its larger single request and the additional migration/verification complexity.

The runtime:

- references only packaged `vazirmatn-*.woff2` files;
- exposes the truthful canonical CSS family `Vazirmatn`;
- retains the public `vazir_font_family` filter as the existing compatibility API for overriding the complete family stack;
- does **not** create a hidden `Vazir` alias for Vazirmatn bytes;
- uses `font-display: swap`;
- performs no default font preloading;
- has no CDN dependency.

Existing callbacks on `vazir_font_family` continue to run unchanged. A callback that deliberately returns the legacy `Vazir` family name remains responsible for providing that family itself; the plugin no longer bundles legacy Vazir binaries under that identity.

### Upgrade and rollback

The persisted option name and schema are unchanged: existing frontend/admin/Gravity compatibility toggles, selected weights, and `exclude_selectors` remain backward compatible. `enable_gravity_forms` remains the stored Gravity-specific compatibility key and is reused by the Gravity Forms, Gravity Flow, and Gravity Perks adapters; Flow additionally respects the existing frontend/admin context toggle for the surface being rendered, while the standalone Perks Settings adapter also requires admin typography to be enabled.

The plugin now keeps the product release version and persisted schema version as separate authorities. `VAZIR_FONT_VERSION` is `1.4.0`, while the unchanged persisted schema remains `VAZIR_FONT_SCHEMA_VERSION = 1.3.0`; upgrading from `1.3.0` to `1.4.0` therefore does not rewrite options merely because the product version changed.

For this personal plugin, rollback is intentionally simple: reinstall/restore the previous compatible plugin revision/package. Because the option schema is unchanged, the prior version can reuse the same saved settings.

## Gravity Forms compatibility

The adapter uses current Gravity Forms APIs for stylesheet delivery:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and narrowly scoped Gravity Forms `!important` rules remain compatibility mechanisms until licensed browser characterization proves equivalent rendering without them. They are not treated as permanently required.

The exact qualified runtime for the current closure evidence is Gravity Forms `3.1.1.1`. Additional direct selectors beyond the existing Theme Framework/current/Legacy form rules are admitted only from rendered exact-runtime failures rather than source similarity.

The plugin does not flush `GFCache`, delete Gravity Forms-generated CSS, delete Gravity Forms transients, or schedule periodic Gravity Forms/font cleanup.

## Gravity Flow compatibility

`VazirFont_GravityFlow_Integration` uses capability-based runtime admission. Runtime admission is not version-gated: when the Gravity Flow runtime is present, the adapter attaches only to `gravityflow_enqueue_admin_scripts` and `gravityflow_enqueue_frontend_scripts`. At the actual enqueue boundary it requires the corresponding host stylesheet handle (`gravityflow_admin_css` or `gravityflow_theme_css`) to be registered. If that capability is absent, the adapter fails closed for that surface rather than applying fallback/global CSS. There is no accepted-version whitelist.

The adapter emits only scoped typography CSS; it adds no JavaScript and does not mutate or replace Inbox data, assignment, authorization, workflow state, search, pagination, filtering, navigation, AG Grid lifecycle, or icon rendering. Its selectors remain narrowly bounded to the AG Grid theme root, material AG text/date inputs, and Flow-owned Flatpickr portal. If a later runtime no longer renders one of those selectors, that rule naturally becomes a no-op.

Real browser/runtime qualification currently covers Gravity Flow `3.1.0`. That qualified package explicitly declares a system stack on `.gflow-grid .ag-theme-alpine`, AG Grid text/date inputs, and the Flow-bound Flatpickr calendar, and the licensed evidence lab verifies the resulting repair and protected icon/exclusion behavior on that exact runtime. A synthetic alternate-version contract proves only that production admission is not controlled by the version string; it is not compatibility proof for that synthetic or any unexecuted Gravity Flow version.

## Gravity Perks compatibility

`VazirFont_GravityPerks_Integration` uses capability-based admission for the standalone Perk Settings document. It attaches only during the supported WordPress `print_styles_array` pass when Gravity Perks has selected and registered its `gwp-admin` stylesheet, returns the host style-handle list unchanged, and adds bounded typography with `wp_add_inline_style( 'gwp-admin', ... )`.

Gravity Perks remains authoritative for routing, Settings rendering, saving, controls, notices, scripts, and host styles. Vazir reuses Loader-owned font delivery and the existing shared settings/exclusion authorities; it does not rewrite the document, replace host assets, or add a JavaScript typography engine.

Real browser/runtime qualification currently covers exact Gravity Perks `2.3.16`. Other versions remain `NOT_PROVEN` until separately exercised. On the exact qualified runtime, the generated Documentation URL aliases to the Settings handler and is not claimed as independent Documentation-page compatibility evidence.

## GravityView boundary

GravityView remains an evidence profile rather than a dedicated production integration layer. The existing exact `3.3.4` regression profile remains part of product-wide qualification, but the in-progress targeted Gutenberg/editor/date/oEmbed qualification and any resulting production repair are intentionally outside `v1.4.0`.

## Requirements and PHP policy

- Minimum WordPress: 6.7
- Minimum PHP: 7.4
- Gravity Forms: optional; current exact browser/runtime qualification covers `3.1.1.1`
- Gravity Flow: optional; runtime admission is capability-based; current exact browser/runtime qualification covers `3.1.0`
- Gravity Perks: optional; standalone Settings admission is capability-based; current exact browser/runtime qualification covers `2.3.16`
- GravityView: no dedicated production adapter; existing regression/evidence profile is bound to `3.3.4`
- Recommended production PHP when Gravity Forms is part of the stack: 8.3, matching current Gravity Forms guidance
- For WordPress-only deployments, current WordPress hosting guidance recommends PHP 8.4 or later
- Latest PHP exercised by this repository CI: 8.5

PHP 8.5 testing by Gravity Forms core and official add-ons is currently documented as pending, so the CI lane is a forward-compatibility check and not a claim that every Gravity Forms deployment should run PHP 8.5.

## Settings

The existing option schema is preserved:

- `enable_frontend`;
- `enable_admin`;
- `enable_gravity_forms`;
- `font_weights`;
- `exclude_selectors`.

`enable_gravity_forms` is retained for stored-option compatibility and acts as the shared Gravity compatibility gate for the Gravity Forms, Gravity Flow, and Gravity Perks adapters. Gravity Flow also requires the corresponding `enable_frontend` or `enable_admin` context to be enabled; standalone Gravity Perks Settings additionally requires `enable_admin`.

`exclude_selectors` means that Vazirmatn `font-family` enforcement must not target matching element roots or their descendants. The runtime implements this as a negative selector boundary; it does not emit competing `font-family` reset declarations for generic element exclusions. Gravity Forms, Flow, and Perks descendant-containment qualification shares `VazirFont_Selector_Boundary`, which treats quoted strings, attributes, and bounded functional selectors lexically, fails unsafe relationships and real element-level `:has()` closed, and does not depend on `ctype_*`.

## Development

```bash
composer install
composer test
composer lint
composer compat
```

`tests/runtime-contract.php` is the standalone core/Gravity Forms contract harness. `tests/version-schema-contract.php` proves that a product release bump does not trigger an options migration when the persisted schema is already current, while older schema state still follows the real migration path. `tests/gravityflow-runtime-contract.php` verifies the dedicated Flow adapter's supported hooks, capability-based host-style dependency gate, version-neutral admission, exclusion behavior, context/settings gates, bounded selector set, and no duplicate `@font-face` delivery. `composer test` runs that contract once with the currently qualified `3.1.0` identity and once with a clearly synthetic alternate version identity; the latter is admission-regression coverage only. `tests/wordpress-smoke.php` is executed by CI against real WordPress installations. `tests/browser-characterization.mjs` verifies computed typography and icon behavior for current WordPress fixtures.

The Product-Wide Reproducible Evidence Lab adds separately diagnosable licensed profiles for Gravity Forms, Gravity Flow, GravityView, Gravity Perks, and the combined Gravity stack. The existing WordPress lanes remain the `wordpress` profile authority. A PASS is scoped to the exact profile/scenarios that ran; package verification or another profile is not a substitute for licensed runtime evidence.

## Licensing

Plugin code is GPL-2.0-or-later. Bundled Vazirmatn font files are distributed with the exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt`; reproducible source identity and SHA-256 digests are recorded in `assets/fonts/Vazirmatn-PROVENANCE.md`.
