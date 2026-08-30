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

Automated PHP and repository contracts verify the loading/API paths. A real-WordPress smoke lane verifies bootstrap and enqueue behavior. Full browser/computed-style equivalence, icon rendering, and licensed Gravity Forms visual coverage remain separate characterization requirements; see `docs/CHARACTERIZATION.md`.

## Font delivery

Version 1.3.0 deliberately keeps the existing bundled static Vazir WOFF2 assets (300, 400, 500, 700, 900). Changing the shipped font binaries or public family identity without a visual baseline would combine an architecture refactor with an unverified typography migration.

The runtime:

- references only packaged `.woff2` files;
- uses `font-display: swap`;
- performs no default font preloading;
- has no CDN dependency;
- keeps the public `vazir_font_family` filter and the `Vazir` family identity.

The upstream Vazirmatn project remains the canonical successor to Vazir. A future Vazirmatn migration should be a separate, characterized change with binary provenance, mixed Persian/Latin rendering checks, and computed-style/visual regression evidence.

## Gravity Forms compatibility

The adapter uses current Gravity Forms APIs for stylesheet delivery:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and narrowly scoped Gravity Forms `!important` rules remain compatibility mechanisms until browser characterization proves equivalent rendering without them. They are not treated as permanently required.

The plugin does not flush `GFCache`, delete Gravity Forms-generated CSS, delete Gravity Forms transients, or schedule periodic Gravity Forms/font cleanup.

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

## Development

```bash
composer install
composer test
composer lint
composer compat
```

`tests/runtime-contract.php` is a standalone contract harness that runs without a WordPress database. `tests/wordpress-smoke.php` is executed by CI against real WordPress installations. Full browser/visual and licensed Gravity Forms characterization are intentionally not represented by these smoke tests.

## Licensing

Plugin code is GPL-2.0-or-later. Bundled font files are distributed with `assets/fonts/OFL.txt` under the SIL Open Font License 1.1.
