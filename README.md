# Vazir Font for WordPress

A self-hosted Persian typography plugin for WordPress with optional Gravity Forms and qualified Gravity Flow compatibility.

## Intended runtime coverage

The plugin is designed to cover:

- WordPress frontend in classic and block themes;
- wp-admin;
- the login screen;
- Block Editor and Site Editor content canvases through `enqueue_block_assets`;
- Gravity Forms frontend, Preview, Form Editor, and No Conflict Mode through registered WordPress style handles;
- currently supported Gravity Forms legacy/current wrapper markup;
- Gravity Flow `3.1.0` Inbox typography through the product's supported admin/frontend enqueue seams, including the AG Grid text root, material text/date inputs, and Flow-bound Flatpickr calendar without replacing host-owned icon families or Inbox behavior.

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

The persisted option name and schema are unchanged: existing frontend/admin/Gravity compatibility toggles, selected weights, and `exclude_selectors` remain backward compatible. `enable_gravity_forms` remains the stored Gravity-specific compatibility key and is also reused by the qualified Gravity Flow adapter; Flow additionally respects the existing frontend/admin context toggle for the surface being rendered. No new settings authority or database migration is introduced.

For this personal plugin, rollback is intentionally simple: reinstall/restore the previous pre-migration plugin revision/package. Because the option schema is unchanged, the prior version can reuse the same saved settings. The migration therefore does not keep a second legacy font payload solely for rollback.

## Gravity Forms compatibility

The adapter uses current Gravity Forms APIs for stylesheet delivery:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and narrowly scoped Gravity Forms `!important` rules remain compatibility mechanisms until licensed browser characterization proves equivalent rendering without them. They are not treated as permanently required.

The plugin does not flush `GFCache`, delete Gravity Forms-generated CSS, delete Gravity Forms transients, or schedule periodic Gravity Forms/font cleanup.

## Gravity Flow compatibility

The dedicated `VazirFont_GravityFlow_Integration` adapter is currently version-bound to the licensed and runtime-qualified Gravity Flow `3.1.0` package. It uses `gravityflow_enqueue_admin_scripts` and `gravityflow_enqueue_frontend_scripts` after Gravity Flow has enqueued its own stable stylesheet handles. The adapter emits only scoped typography CSS; it adds no JavaScript and does not mutate or replace Inbox data, assignment, authorization, workflow state, search, pagination, filtering, navigation, AG Grid lifecycle, or icon rendering.

The repair is intentionally narrow: Gravity Flow `3.1.0` explicitly declares a system stack on `.gflow-grid .ag-theme-alpine`, AG Grid text/date inputs, and the Flow-bound Flatpickr calendar. Vazir corrects those material text surfaces while leaving `agGridAlpine`, `gflow-icons-common`, Dashicons, and other host-owned glyph families untouched. It consumes the same `exclude_selectors` authority and fails closed for inheritable rules when an exclusion cannot be represented safely.

GravityView remains an evidence profile rather than a dedicated production integration layer.

## Requirements and PHP policy

- Minimum WordPress: 6.7
- Minimum PHP: 7.4
- Gravity Forms: optional
- Gravity Flow: optional; dedicated typography compatibility currently qualified for `3.1.0`
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

`enable_gravity_forms` is retained for stored-option compatibility and acts as the shared Gravity compatibility gate for the existing Gravity Forms adapter and the qualified Gravity Flow adapter. Gravity Flow also requires the corresponding `enable_frontend` or `enable_admin` context to be enabled.

`exclude_selectors` means that Vazirmatn `font-family` enforcement must not target matching element roots or their descendants. The runtime implements this as a negative selector boundary; it does not emit competing `font-family` reset declarations for generic element exclusions.

## Development

```bash
composer install
composer test
composer lint
composer compat
```

`tests/runtime-contract.php` is the standalone core/Gravity Forms contract harness. `tests/gravityflow-runtime-contract.php` verifies the dedicated Flow adapter's supported hooks, host-style dependencies, exclusion behavior, context/settings gates, bounded selector set, and no duplicate `@font-face` delivery. `tests/wordpress-smoke.php` is executed by CI against real WordPress installations. `tests/browser-characterization.mjs` verifies computed typography and icon behavior for current WordPress fixtures.

The Product-Wide Reproducible Evidence Lab adds separately diagnosable licensed profiles for Gravity Forms, Gravity Flow, GravityView, and the combined Gravity stack. The existing WordPress lanes remain the `wordpress` profile authority. A PASS is scoped to the exact profile/scenarios that ran; package verification or another profile is not a substitute for licensed runtime evidence.

## Licensing

Plugin code is GPL-2.0-or-later. Bundled Vazirmatn font files are distributed with the exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt`; reproducible source identity and SHA-256 digests are recorded in `assets/fonts/Vazirmatn-PROVENANCE.md`.
