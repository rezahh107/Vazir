# Release Checklist

1. Update the plugin version in `vazir-font-wp.php` and the `VAZIR_FONT_VERSION` constant.
2. Review `README.md` for changelog notes and licensing accuracy.
3. Regenerate the translation template:
   ```bash
   wp i18n make-pot . languages/vazir-font-wp.pot
   ```
4. Verify that all bundled font files and `assets/fonts/OFL.txt` are present.
5. Run coding standards before tagging:
   ```bash
   vendor/bin/phpcs --standard=WordPress --extensions=php,inc .
   ```
6. Test toggling frontend, admin, login, and Gravity Forms options on a WordPress site (WP 5.8+, PHP 7.4+).
7. Commit, tag (e.g. `git tag v1.1.0`), and push the release.
