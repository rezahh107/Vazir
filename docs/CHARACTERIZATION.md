# Characterization Baseline

Initial repository state:

- repository: `rezahh107/Vazir`
- branch: `main`
- SHA: `4e229a228aa130889ffe0a72778eee7e0a83a554`
- plugin version: `1.2.0`

## Directly confirmed from source/package

| Component | Baseline classification | Evidence summary |
|---|---|---|
| Bootstrap/options | ACTIVE_REQUIRED | plugin singleton, bounded SPL autoloader, persisted `vazir_font_options` |
| Frontend loader | ACTIVE_REQUIRED | `wp_enqueue_scripts` + head CSS path |
| Admin/login loader | ACTIVE_REQUIRED | global admin/login typography options |
| Block Editor path | ACTIVE_BUT_REFACTORABLE | used `enqueue_block_editor_assets`; incomplete for WordPress 7.1 always-iframed content canvas |
| Static font files | ACTIVE_REQUIRED | WOFF2 files for 300/400/500/700/900 |
| WOFF/TTF generated sources | RISKY | generated URLs had no matching packaged files |
| Five-weight preload | ACTIVE_BUT_REFACTORABLE | all selected/default weights were preloaded |
| GF `gform_enqueue_scripts` | ACTIVE_REQUIRED | frontend integration entrypoint |
| GF `gform_preview_styles` | ACTIVE_BUT_REFACTORABLE | current callback returned raw CSS rather than style handles |
| GF `gform_noconflict_styles` | ACTIVE_BUT_REFACTORABLE | current handle was not a registered stylesheet handle |
| GF `gform_field_content` | UNKNOWN | aggressive rendering workaround; no licensed Gravity Forms browser proof available for removal |
| GF `gform_field_css_class` | UNKNOWN | compatibility behavior; no licensed Gravity Forms browser proof available for removal |
| PHP `add_action( 'gform_post_render', ... )` | DEAD_CONFIRMED | official API is a JavaScript event, not a PHP action |
| GF cache/file deletion | RISKY | unrelated cache/file ownership; no plugin-generated persistent CSS cache existed |
| weekly cron | REDUNDANT_PROVEN | only triggered the unrelated GF cache/file cleanup path |
| Composer `vendor/` | DEV_ONLY | plugin runtime uses its own bounded autoloader; Composer packages are development tooling |
| Tests/CI | DEAD_CONFIRMED at initial SHA | `composer test` existed but repository had no `tests/` and no CI workflow |

## Browser characterization now present on the refactor branch

The refactor branch contains Chromium/Playwright computed-style characterization against WordPress 7.1 with both Twenty Twenty-One and Twenty Twenty-Five. The fixture checks frontend text and controls, login, wp-admin, Dashicons, the Post Editor iframe, and the Site Editor canvas for the block-theme lane.

The exclusion regression fixture configures `.vf-excluded-component` in the existing `exclude_selectors` option and gives the excluded text an explicit `monospace` family. The browser contract requires ordinary neighboring typography to remain Vazir while the excluded text remains non-Vazir. This is the runtime evidence required for the negative applicability repair; source inspection alone is not sufficient.

Exact-Head success must always be observed from the CI run bound to the resulting commit before claiming this characterization passed for a change.

Licensed Gravity Forms browser characterization remains separate and environment-dependent. Until that evidence exists, the refactor must not remove `gform_field_content`, `gform_field_css_class`, or the scoped Gravity Forms `!important` compatibility rules merely for architectural simplification.

## Exclusion semantics

`exclude_selectors` is a negative applicability boundary for Vazir `font-family` enforcement. Element-level exclusions are incorporated into generated enforcement selectors so those rules do not match the excluded root or elements below it. The Loader does not implement generic exclusions by emitting competing `font-family: inherit`, `initial`, `revert`, or `revert-layer` reset declarations.

Pseudo-element exclusions are not forced into relational `:where()`/`:not()` guards. Generic Vazir enforcement does not directly target pseudo-elements, and existing dedicated icon-family protections remain responsible for Dashicons and equivalent icon contexts.

The Gravity Forms adapter consumes the same `vazir_font_options['exclude_selectors']` authority. Its Theme Framework custom-property rule and legacy/current `font-family` compatibility rules use the same root/descendant negative applicability semantics, with an additional `:has(:where(...))` guard on inheritable rules so a rule on an ancestor cannot leak Vazir into an excluded descendant subtree. If an accepted exclusion itself contains `:has()`, the adapter omits the affected inheritable GF rule rather than nesting `:has()` into invalid CSS or approximating selector matching in PHP.

`gform_field_content` remains registered, but inline `font-family` cleanup is now conditional. When any accepted element-level exclusion exists, the callback preserves the field markup unchanged because arbitrary CSS-selector matching cannot be truthfully reproduced against a rendering fragment with a bounded PHP regex/DOM workaround. Cleanup is retained only when no element-level exclusion boundary is active. This keeps the compatibility hook without letting it destroy an excluded component's own font declaration.

## Removed/changed mechanisms that do not depend on licensed Gravity Forms visual equivalence

- nonexistent WOFF/TTF sources: correctness defect, replaced by packaged WOFF2 only;
- private Loader method access from GF adapter: PHP correctness defect, replaced by explicit public read-only Loader surfaces;
- raw CSS passed through style-handle filters: API contract defect, replaced with a registered WordPress style handle;
- `gform_post_render` registered as PHP action: unreachable/misbound API, removed;
- GF global cache/file/transient cleanup and weekly cron: plugin had no persistent generated cache that required them;
- editor content hook: replaced with official `enqueue_block_assets` path for current iframe editors;
- default multi-weight preload: removed; CSS discovery is retained and no rendering coverage depends on preload.
