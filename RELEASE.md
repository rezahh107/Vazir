# Release Checklist

1. Confirm the release branch is based on the intended `main` SHA.
2. Update plugin/version metadata consistently in `vazir-font-wp.php`.
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
7. Verify `exclude_selectors` as a negative applicability boundary: an excluded component with an explicit non-Vazir family must retain that family while neighboring text remains Vazir; no generic exclusion `font-family` reset may be emitted.
8. With a licensed current Gravity Forms build, test Orbital/Theme Framework, supported Legacy Markup, Preview, Form Editor, No Conflict Mode, AJAX, multi-page navigation, validation rerenders, conditional logic, representative Gravity Forms icons, and both wrapper-level and descendant-level `exclude_selectors` behavior.
   - Record the exact Gravity Forms version actually installed and tested.
   - Use real browser/computed-style observations for Gravity Forms claims.
   - Repository stubs, source inspection, and unlicensed WordPress/browser CI are not substitutes for licensed Gravity Forms characterization.
   - If the licensed environment is unavailable, record `NOT_EXECUTED_ENVIRONMENT_UNAVAILABLE`; do not report PASS.
9. Inspect computed `font-family` on representative text/form controls and verify Dashicons/Gravity Forms icons remain intact.
10. Compare font request count/bytes and ensure no duplicate downloads.
11. Verify no Gravity Forms cache/file deletion and no periodic font cleanup cron are present.
12. Confirm CI success is bound to the exact release candidate Head; do not carry success forward across a changed Head without rerunning/rechecking affected evidence.
13. Build the production artifact without `vendor/`, tests, CI files, or development tooling unless explicitly required by the release process.
14. Do not publish or merge without Owner authorization.
