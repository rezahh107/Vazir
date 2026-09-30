# Gravity Perks compatibility characterization

## Qualified boundary

This characterization is bound to the exact licensed runtime exercised by the Product Evidence Lab:

- Gravity Perks `2.3.16` (`gravityperks_2.3.16.zip`, SHA-256 `a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c`);
- Gravity Forms prerequisite `3.1.1.1` (SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`).

Production admission is capability-based. Compatibility evidence is not: other Gravity Perks versions remain `NOT_PROVEN` until separately exercised.

## Pre-repair authority

Merged PR #18 is the canonical pre-repair qualification authority. It established that ordinary Gravity Perks wp-admin already received Vazirmatn, while the reachable standalone Perk Settings document produced by `GWPerksPage::load_perk_settings()` did not. The standalone document printed Gravity Perks' `gwp-admin` stylesheet through an early direct `wp_print_styles()` call and did not contain Vazir font delivery.

PR #18 also falsified the earlier Documentation assumption for exact 2.3.16. Although `load_documentation()` remains in source, a truthy Perks `view` dispatches to `load_perk_settings()`. The generated Documentation URL therefore serves Settings and remains `NOT_REACHABLE_AS_DOCUMENTATION`. Google Fonts markup in the dead legacy Documentation implementation is source-only evidence, not a reachable runtime defect; no `fonts.googleapis.com` or `fonts.gstatic.com` request was observed on the reachable surfaces.

## Production seam

The repair uses WordPress' `print_styles_array` filter. This seam runs inside `WP_Styles::all_deps()` while Gravity Perks processes its own early standalone `wp_print_styles()` request, so it survives the fact that `load_perk_settings()` removes `wp_print_styles` actions before printing.

`VazirFont_GravityPerks_Integration` is initialized during Vazir's existing `plugins_loaded` bootstrap only when the Gravity Perks runtime class is available. At the print boundary it fails closed unless all of these capabilities are present:

- an admin request;
- real Gravity Perks runtime and `GWPerksPage::load_perk_settings()`;
- `page=gwp_perks`;
- a non-empty `view` and Perk `slug`;
- `gwp-admin` present in the styles being processed;
- `gwp-admin` registered by the host;
- existing `enable_admin` and `enable_gravity_forms` preferences enabled.

The filter returns the host style-handle list unchanged. It uses `wp_add_inline_style()` to attach the correction to the already-registered `gwp-admin` handle; it does not rewrite `<link>` markup, buffer or rewrite the response, edit Gravity Perks, replace the Settings document, or inject JavaScript.

## Font and selector ownership

The adapter reuses `VazirFont_Loader::get_font_face_css()` and the existing `vazir_font_family` filter. It introduces no second font directory or CDN dependency. Selected bundled Vazirmatn weights continue to come from Loader semantics.

Typography enforcement is limited to real standalone Settings text surfaces rooted under `body.perk-iframe .perk-settings`: page title, labels, descriptions, text-bearing inputs, selects, textareas when present, and the save button. Checkbox/radio glyphs and host icon pseudo-elements are not treated as text typography.

The existing `vazir_font_options['exclude_selectors']` setting remains the sole exclusion authority. For every inheritable Gravity Perks typography selector, representable element exclusions are applied through both root/descendant negative applicability and descendant-containment protection. The root/descendant guard always keeps the configured selector in its original document context.

Descendant containment is different because selectors inside `:has()` are relative to the targeted Settings node. Local simple, compound, attribute, and safely bounded functional selectors can be embedded unchanged. An exclusion beginning with the exact guaranteed Settings scope, such as `.perk-settings .no-vazir`, is safely reduced to `.no-vazir` only for the descendant-containment predicate because every bounded production target is already inside `.perk-settings`; the original `.perk-settings .no-vazir` remains in the root/descendant guard. Other top-level descendant/child/sibling relationships cannot be proven equivalent after target-relative embedding and therefore fail the bounded inheritable repair closed rather than being broadened, narrowed, or reinterpreted.

The qualification is lexical rather than a general CSS parser: quoted strings, attribute selectors, and functional pseudo-class parentheses are opaque while top-level combinators are classified. Combinator-looking characters or whitespace inside those bounded regions therefore do not cause false rejection. Pseudo-element exclusions remain host-owned and are not inserted into relational element guards. Element exclusions containing `:has()` remain fail-closed because the required descendant-containment boundary would otherwise nest relational selectors. The repair does not use competing `font-family: inherit`, `initial`, `revert`, or `revert-layer` reset rules.

## Evidence contract

The dedicated `gravityperks` Product Evidence profile is authoritative for this repair. It exercises:

- normal wp-admin body, heading, real Perk listing, and action link;
- exact source/dispatcher semantics;
- the generated Documentation alias;
- standalone Settings computed families for title, label, description, text input, select, save button, and textarea when rendered;
- a direct excluded Settings text surface;
- an ancestor-qualified configured exclusion `.perk-settings .vazir-gp-evidence-excluded-nested` whose matching child is inside an otherwise targeted description, plus a separate non-excluded Settings text surface;
- actual bundled Vazirmatn WOFF2 requests, configured Loader weights, and duplicate URL detection;
- continued `gwp-admin-css` host ownership and supported WordPress style-pipeline sentinels;
- absence of an unnecessary standalone Vazir stylesheet link;
- protected Dashicons / GFFontAwesome / FontAwesome families when actually rendered, otherwise explicitly `NOT_EXERCISED`;
- real text/select/checkbox Settings save persistence and resulting notice/page lifecycle;
- absence of reachable Google Fonts/gstatic requests.

The ancestor-qualified browser scenario requires both the targeted containing description and the matching excluded descendant to remain non-Vazirmatn while a separate non-excluded description remains Vazirmatn. That combination proves the relative descendant predicate blocks inheritance across the configured document-context exclusion without disabling ordinary Settings typography.

A post-repair `PASS` means only that exact 2.3.16 and the exercised material surfaces are admitted. It does not turn the unreachable legacy Documentation implementation into supported runtime evidence and does not prove later Gravity Perks releases.
