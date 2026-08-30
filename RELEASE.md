# Release Checklist

1. Confirm the release branch is based on the intended `main` SHA.
2. Update plugin/version metadata consistently.
3. Run:
   ```bash
   composer install
   composer test
   composer lint
   composer compat
   ```
4. Verify every generated `@font-face` URL resolves to a packaged WOFF2 asset.
5. Verify default output contains no font preload unless a measured release requirement explicitly adds one.
6. Test WordPress frontend, wp-admin, login, Block Editor iframe, and Site Editor canvas.
7. With a licensed current Gravity Forms build, test Orbital/Theme Framework, supported Legacy Markup, Preview, Form Editor, No Conflict Mode, AJAX, multi-page navigation, validation rerenders, and conditional logic.
8. Inspect computed `font-family` on representative text/form controls and verify Dashicons/Gravity Forms icons remain intact.
9. Compare font request count/bytes and ensure no duplicate downloads.
10. Verify no Gravity Forms cache/file deletion and no periodic font cleanup cron are present.
11. Build the production artifact without `vendor/`, tests, CI files, or development tooling unless explicitly required by the release process.
12. Do not publish or merge without Owner authorization.
