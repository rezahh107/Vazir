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
| GF `gform_field_content` | UNKNOWN | aggressive rendering workaround; no browser proof available for removal |
| GF `gform_field_css_class` | UNKNOWN | compatibility behavior; no browser proof available for removal |
| PHP `add_action( 'gform_post_render', ... )` | DEAD_CONFIRMED | official API is a JavaScript event, not a PHP action |
| GF cache/file deletion | RISKY | unrelated cache/file ownership; no plugin-generated persistent CSS cache existed |
| weekly cron | REDUNDANT_PROVEN | only triggered the unrelated GF cache/file cleanup path |
| Composer `vendor/` | DEV_ONLY | plugin runtime uses its own bounded autoloader; Composer packages are development tooling |
| Tests/CI | DEAD_CONFIRMED | `composer test` existed but repository had no `tests/` and no CI workflow |

## Visual/browser baseline

`NOT_PROVEN` in this execution environment. No real browser automation, WordPress 7.1 site, licensed Gravity Forms 3.1.x artifact, or network waterfall was available to execute the full requested matrix.

Because of that evidence ceiling, the refactor must not remove `gform_field_content`, `gform_field_css_class`, or the scoped Gravity Forms `!important` compatibility rules in this pass. They require a future real-browser regression run before removal.

## Removed/changed mechanisms that do not depend on visual equivalence

- nonexistent WOFF/TTF sources: correctness defect, replaced by packaged WOFF2 only;
- private Loader method access from GF adapter: PHP correctness defect, replaced by explicit public read-only Loader surfaces;
- raw CSS passed through style-handle filters: API contract defect, replaced with a registered WordPress style handle;
- `gform_post_render` registered as PHP action: unreachable/misbound API, removed;
- GF global cache/file/transient cleanup and weekly cron: plugin had no persistent generated cache that required them;
- editor content hook: replaced with official `enqueue_block_assets` path for current iframe editors;
- default multi-weight preload: removed; CSS discovery is retained and no rendering coverage depends on preload.
