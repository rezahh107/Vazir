# Vazir Font for WordPress

A self-hosted Persian typography plugin for WordPress with optional Gravity Forms integration.

## Intended runtime coverage

The plugin is designed to cover:

- WordPress frontend in classic and block themes;
- wp-admin;
- the login screen;
- Block Editor and Site Editor content canvases through `enqueue_block_assets`;
- Gravity Forms frontend, Preview, Form Editor, and No Conflict Mode through registered WordPress style handles;
- currently supported Gravity Forms legacy/current wrapper markup.

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

The persisted option name and schema are unchanged: existing frontend/admin/Gravity Forms toggles, selected weights, and `exclude_selectors` retain their previous meaning. Upgrading replaces only the bundled typeface/default family behavior; it does not reinterpret user options or require a database migration subsystem.

For this personal plugin, rollback is intentionally simple: reinstall/restore the previous pre-migration plugin revision/package. Because the option schema is unchanged, the prior version can reuse the same saved settings. The migration therefore does not keep a second legacy font payload solely for rollback.

## Gravity Forms compatibility

The adapter uses current Gravity Forms APIs for stylesheet delivery:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and narrowly scoped Gravity Forms `!important` rules remain compatibility mechanisms until licensed browser characterization proves equivalent rendering without them. They are not treated as permanently required.

The plugin does not flush `GFCache`, delete Gravity Forms-generated CSS, delete Gravity Forms transients, or schedule periodic Gravity Forms/font cleanup.

Gravity Flow and GravityView are currently evidence profiles, not dedicated production integration layers. Their licensed profiles characterize how the bundled typography coexists with the exact Owner-supplied products without inventing Flow/View-specific CSS or runtime ownership.

## Requirements and PHP policy

- Minimum WordPress: 6.7
- Minimum PHP: 7.4
- Gravity Forms: optional
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

`exclude_selectors` means that Vazirmatn `font-family` enforcement must not target matching element roots or their descendants. The runtime implements this as a negative selector boundary; it does not emit competing `font-family` reset declarations for generic element exclusions.

## Development

```bash
composer install
composer test
composer lint
composer compat
```

`tests/runtime-contract.php` is a standalone contract harness that runs without a WordPress database. `tests/wordpress-smoke.php` is executed by CI against real WordPress installations. `tests/browser-characterization.mjs` verifies computed typography and icon behavior for current WordPress fixtures.

The Product-Wide Reproducible Evidence Lab adds separately diagnosable licensed profiles for Gravity Forms, Gravity Flow, GravityView, and the combined Gravity stack. The existing WordPress lanes remain the `wordpress` profile authority. A PASS is scoped to the exact profile/scenarios that ran; package verification or another profile is not a substitute for licensed runtime evidence.

## Licensing

Plugin code is GPL-2.0-or-later. Bundled Vazirmatn font files are distributed with the exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt`; reproducible source identity and SHA-256 digests are recorded in `assets/fonts/Vazirmatn-PROVENANCE.md`.
