# Characterization Baseline

## Initial repository state

- repository: `rezahh107/Vazir`
- baseline branch: `main`
- baseline SHA: `4e229a228aa130889ffe0a72778eee7e0a83a554`
- baseline plugin version: `1.2.0`

## Directly confirmed from the initial source/package

| Component | Baseline classification | Evidence summary |
| --- | --- | --- |
| Bootstrap/options | ACTIVE_REQUIRED | plugin singleton, bounded SPL autoloader, persisted `vazir_font_options` |
| Frontend loader | ACTIVE_REQUIRED | `wp_enqueue_scripts` + head CSS path |
| Admin/login loader | ACTIVE_REQUIRED | global admin/login typography options |
| Block Editor path | ACTIVE_BUT_REFACTORABLE | used `enqueue_block_editor_assets`; incomplete for WordPress 7.1 always-iframed content canvas |
| Static font files | ACTIVE_REQUIRED | WOFF2 files for 300/400/500/700/900 |
| WOFF/TTF generated sources | RISKY | generated URLs had no matching packaged files |
| Five-weight preload | ACTIVE_BUT_REFACTORABLE | all selected/default weights were preloaded |
| GF `gform_enqueue_scripts` | ACTIVE_REQUIRED | frontend integration entrypoint |
| GF `gform_preview_styles` | ACTIVE_BUT_REFACTORABLE | callback returned raw CSS rather than style handles |
| GF `gform_noconflict_styles` | ACTIVE_BUT_REFACTORABLE | current handle was not a registered stylesheet handle |
| GF `gform_field_content` | UNKNOWN | aggressive rendering workaround; no licensed Gravity Forms browser proof available for removal |
| GF `gform_field_css_class` | UNKNOWN | compatibility behavior; no licensed Gravity Forms browser proof available for removal |
| PHP `add_action( 'gform_post_render', ... )` | DEAD_CONFIRMED | official API is a JavaScript event, not a PHP action |
| GF cache/file deletion | RISKY | unrelated cache/file ownership; no plugin-generated persistent CSS cache existed |
| weekly cron | REDUNDANT_PROVEN | only triggered the unrelated GF cache/file cleanup path |
| Composer `vendor/` | DEV_ONLY | plugin runtime uses its own bounded autoloader; Composer packages are development tooling |
| Tests/CI | DEAD_CONFIRMED at initial SHA | `composer test` existed but repository had no `tests/` and no CI workflow |

## Merged characterized implementation

The modernization/refactor work was merged through PR #11 on 2026-08-30.

- reviewed implementation Head: `642231ba3ee9635f2eebe184fabc462142207152`
- merge commit on `main`: `148810ad9935e08723fece247bf2411739409654`
- plugin version: `1.3.0`
- the merge commit used the same repository tree as the reviewed implementation Head
- exact-Head push CI passed before merge
- PR-triggered CI run 55 also completed successfully on the reviewed Head before merge

The merged CI/browser characterization includes Chromium/Playwright computed-style checks against WordPress 7.1 with both Twenty Twenty-One and Twenty Twenty-Five. The fixture checks frontend text and controls, login, wp-admin, Dashicons, the Post Editor iframe, and the Site Editor canvas for the block-theme lane.

The exclusion regression fixture configures `.vf-excluded-component` in the existing `exclude_selectors` option and gives the excluded text an explicit `monospace` family. The browser contract requires ordinary neighboring typography to remain Vazir while the excluded text remains non-Vazir. This is runtime evidence for the generic negative-applicability repair; source inspection alone is not treated as equivalent proof.

Exact-Head success must always be observed from CI bound to the commit being evaluated before claiming characterization passed for a later change.

## PR #12 evidence-lab evolution

PR #12 began as a dedicated licensed Gravity Forms evidence lab without changing production PHP or CSS behavior. That first batch established exact-Head checkout, ephemeral WordPress/database provisioning, fail-closed package verification, deterministic real-Gravity-Forms fixtures, runtime identity capture, Playwright/Chromium characterization, bounded diagnostics, and a future production-ZIP substitution seam.

The Gravity Forms profile remains deep and unchanged in intended coverage: Orbital / Theme Framework, supported Legacy Markup, text/control typography, exclusions, representative icon families, conditional logic, AJAX validation rerender, multi-page navigation, Preview, Form Editor, No Conflict Mode, and duplicate-font-request characterization.

The Owner-approved scope amendment generalizes only the reusable infrastructure into a **Vazir Product-Wide Reproducible Evidence Lab**. The existing WordPress smoke/browser lanes remain authoritative for the `wordpress` profile and are not duplicated. Licensed matrix profiles are `gravityforms`, `gravityflow`, `gravityview`, and `gravity-stack`.

### Pinned Owner-supplied package identities

The generic verifier was executed directly against the actual Owner-supplied ZIP bytes and accepted all three exact identities. Deterministic wrong-size, wrong-SHA, and missing-entrypoint checks were also executed and rejected as required.

| Package | Drive ID | Size | SHA-256 | Entrypoint | Header version |
| --- | --- | ---: | --- | --- | --- |
| Gravity Forms | `10pDROZVyqELKzSzIiJrOyEWKjxS8r22Q` | `5300290` | `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b` | `gravityforms/gravityforms.php` | `3.1.1.1` |
| Gravity Flow | `1F5p8XvXdfrKSpm-_cozxIAX_eJLZRuOV` | `2603034` | `ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404` | `gravityflow/gravityflow.php` | `3.1.0` |
| GravityView | `1sFuTdH7E0SVqAMPT0V7wOBoaYPfqgoKj` | `7569755` | `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829` | `gravityview/gravityview.php` | `3.3.4` |

The approved GravityView bytes are not described as a vanilla upstream archive. The exact `gravityview/gravityview.php` in the Owner-supplied package contains a custom `pre_http_request` filter that redirects POST requests for `store.gravitykit.com` to `gravitykit.gpltimes.com`. Evidence is therefore bound to the exact SHA-256 above; the lab does not substitute another package and does not require a licensing request to create its synthetic View fixture.

### Profile claim ceilings

- `wordpress`: existing WordPress smoke/browser characterization only.
- `gravityforms`: deep real Gravity Forms typography/dynamic/admin characterization; it proves only the scenarios that execute in that profile.
- `gravityflow`: exact GF + Flow + Vazir activation, a real `Gravity_Flow_API` approval step/entry, current inbox surfaces, prerequisite GF rendering, exclusions, and rendered icon-family checks when the real node exists. Fixture-created workflow state does not prove every production setup path.
- `gravityview`: exact GF + GravityView + Vazir activation, a real `gravityview` post bound to a synthetic GF form using inspected 3.3.4 View metadata, front-end View/search/result state, admin editor, exclusions, pagination/icon checks when rendered. AJAX remains `NOT_PROVEN` unless an observable runtime path is actually exercised.
- `gravity-stack`: representative coexistence/regression checks with all three Gravity products and Vazir active. It is not exhaustive compatibility evidence for any individual product.

A PASS in one profile must not be promoted into another profile or an unexecuted surface.

## Current verification boundary

The first PR #12 GitHub Actions attempts on 2026-09-19 were `ENVIRONMENT_UNAVAILABLE`: GitHub completed both the licensed lab and every job in the pre-existing CI workflow without allocating a runner (`runner_id=0`, no executed steps). This is an execution-environment failure, not a Vazir or Gravity-product runtime result.

Until a later exact-Head run actually receives a runner, licensed browser/runtime scenarios remain `NOT_EXECUTED / ENVIRONMENT_UNAVAILABLE` and therefore `NOT_PROVEN`. The existence of the workflow, successful local package-identity verification, source inspection, or fixture code is not a runtime PASS.

Repository/runtime contract tests with stubs are not a substitute for licensed product runtime evidence. In particular, Gravity Forms Orbital/Theme Framework, Legacy Markup, Preview, Form Editor, No Conflict Mode, AJAX, multi-page navigation, validation rerenders, conditional logic, and representative icons remain `NOT_PROVEN` until their profile actually executes.

Until that evidence exists, `gform_field_content`, `gform_field_css_class`, and the scoped Gravity Forms `!important` compatibility rules must not be removed merely for architectural simplification.

## Exclusion semantics

`exclude_selectors` is a negative applicability boundary for Vazir `font-family` enforcement. Element-level exclusions are incorporated into generated enforcement selectors so those rules do not match the excluded root or elements below it. The Loader does not implement generic exclusions by emitting competing `font-family: inherit`, `initial`, `revert`, or `revert-layer` reset declarations.

Pseudo-element exclusions are not forced into relational `:where()`/`:not()` guards. Generic Vazir enforcement does not directly target pseudo-elements, and existing dedicated icon-family protections remain responsible for Dashicons and equivalent icon contexts.

The Gravity Forms adapter consumes the same `vazir_font_options['exclude_selectors']` authority. Its Theme Framework custom-property rule and legacy/current `font-family` compatibility rules use the same root/descendant negative applicability semantics, with an additional `:has(:where(...))` guard on inheritable rules so a rule on an ancestor cannot leak Vazir into an excluded descendant subtree.

If an accepted exclusion itself contains `:has()`, the adapter omits the affected inheritable Gravity Forms rule rather than nesting `:has()` into invalid CSS or approximating selector matching in PHP.

`gform_field_content` remains registered, but inline `font-family` cleanup is conditional. When any accepted element-level exclusion exists, the callback preserves field markup unchanged because arbitrary CSS-selector matching cannot be truthfully reproduced against a rendering fragment with a bounded PHP regex/DOM workaround. Cleanup is retained only when no element-level exclusion boundary is active.

## Removed/changed mechanisms that do not depend on licensed Gravity Forms visual equivalence

- nonexistent WOFF/TTF sources: correctness defect, replaced by packaged WOFF2 only;
- private Loader method access from the Gravity Forms adapter: PHP correctness defect, replaced by explicit public read-only Loader surfaces;
- raw CSS passed through style-handle filters: API contract defect, replaced with a registered WordPress style handle;
- `gform_post_render` registered as a PHP action: unreachable/misbound API, removed;
- Gravity Forms global cache/file/transient cleanup and weekly cron: plugin had no persistent generated cache that required them;
- editor content hook: replaced with the official `enqueue_block_assets` path for current iframe editors;
- default multi-weight preload: removed; CSS discovery is retained and no rendering coverage depends on preload.

## Evidence discipline for future changes

Distinguish source/repository contracts, real WordPress smoke, computed-style browser evidence, package identity, and each licensed product profile. Package identity PASS proves only exact package identity/archive safety. `ENVIRONMENT_UNAVAILABLE`, `NOT_EXECUTED`, and `NOT_PROVEN` remain evidence gaps, not PASS and not reproduced product defects.
