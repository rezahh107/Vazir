# Vazir Font for WordPress

A lightweight WordPress plugin that applies the Vazir typeface to the frontend, wp-admin area, login screen, and optional Gravity Forms instances.

## Features

- Toggle font loading for frontend, admin dashboard, and Gravity Forms.
- Choose which font weights (300, 400, 500, 700, 900) should be enqueued.
- Exclude individual CSS selectors from inheriting the Vazir font.
- Preloads bundled `.woff2` font files for faster rendering with `font-display: swap`.

## Licensing

- **Plugin code** is licensed under [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
- **Vazir font files** (`assets/fonts/*.woff2`) are licensed under the [SIL Open Font License 1.1](assets/fonts/OFL.txt). The typeface name must remain "Vazir" in all redistributions.

## Development

1. Install dependencies: `composer install`.
2. Run coding standards: `vendor/bin/phpcs --standard=WordPress --extensions=php,inc .`.
3. Generate translations: `wp i18n make-pot . languages/vazir-font-wp.pot`.

## Support

Please file issues or feature requests via the repository issue tracker.
