# Vazir Font for WordPress

A self-hosted Persian typography plugin for WordPress and optional Gravity Forms integration.

## Runtime coverage

- WordPress frontend and classic/block themes
- wp-admin
- login screen
- Block Editor and Site Editor content canvas through `enqueue_block_assets`
- Gravity Forms frontend, Preview, Form Editor, and No Conflict Mode through registered WordPress style handles
- supported Gravity Forms legacy/current wrapper markup

## Font delivery

Version 1.3.0 keeps the existing bundled static Vazir WOFF2 assets (300, 400, 500, 700, 900) to avoid an unverified font-identity migration during the architecture hardening pass.

The runtime:

- references only packaged `.woff2` files;
- uses `font-display: swap`;
- performs no default font preloading;
- has no CDN dependency;
- keeps the public `vazir_font_family` filter and the `Vazir` family identity.

A future Vazirmatn migration must include binary provenance and visual/computed-style regression evidence before changing the public font identity.

## Gravity Forms compatibility

The adapter uses current Gravity Forms APIs for stylesheet delivery:

- `gform_enqueue_scripts`
- `gform_preview_styles`
- `gform_noconflict_styles`

`gform_field_content` and `gform_field_css_class` remain as compatibility mechanisms until browser characterization proves equivalent rendering without them. The plugin does not flush `GFCache`, delete Gravity Forms generated CSS, delete Gravity Forms transients, or schedule periodic Gravity Forms/font cleanup.

## Requirements

- WordPress 6.7+
- PHP 7.4+
- Gravity Forms is optional; when used, current supported Gravity Forms versions are recommended

For new WordPress 7.1 installations, WordPress recommends PHP 8.4/8.5. Gravity Forms currently recommends PHP 8.3, so production PHP selection should follow the full site stack rather than the newest PHP number alone.

## Settings

The existing option schema is preserved:

- `enable_frontend`
- `enable_admin`
- `enable_gravity_forms`
- `font_weights`
- `exclude_selectors`

## Development

```bash
composer install
composer test
composer lint
composer compat
```

`tests/runtime-contract.php` is a standalone contract harness that can run without a WordPress database. Full browser/visual validation still requires a real WordPress + Gravity Forms test environment; see `docs/CHARACTERIZATION.md`.

## Licensing

Plugin code is GPL-2.0-or-later. Bundled font files are distributed with `assets/fonts/OFL.txt` under the SIL Open Font License 1.1.
