# Gravity Perks evidence profile

This profile is exact-version qualification infrastructure for the Owner-supplied Gravity Perks 2.3.16 package. Production admission is capability-based, but browser/runtime evidence from this profile applies only to exact 2.3.16; other Gravity Perks releases remain `NOT_PROVEN` until separately exercised.

The profile creates an ephemeral real Perk plugin using the `Perk: True` header and the exact runtime `GWPerk` / `GP_Perk` mechanism. The fixture exists only inside the temporary WordPress lab and is never part of the Vazir production artifact.

PR #18 established the pre-repair runtime boundary: ordinary Gravity Perks wp-admin already resolved to Vazirmatn, while the reachable standalone Settings document produced by `GWPerksPage::load_perk_settings()` did not receive Vazir typography. Exact 2.3.16 also keeps a legacy `load_documentation()` implementation, but its generated Documentation URL dispatches to the Settings handler and is therefore `NOT_REACHABLE_AS_DOCUMENTATION`.

The production repair uses WordPress `print_styles_array`, which runs inside `WP_Styles::all_deps()` during Gravity Perks' own early `wp_print_styles()` call even after Gravity Perks removes `wp_print_styles` actions. The adapter leaves Gravity Perks' style handle list unchanged and attaches bundled `@font-face` rules plus bounded Settings typography CSS to the already-registered `gwp-admin` handle through `wp_add_inline_style()`. It does not rewrite HTML, buffer output, edit Gravity Perks, create a replacement Settings document, or introduce a standalone Vazir stylesheet link.

The profile independently proves:

- ordinary Gravity Perks wp-admin remains healthy and does not receive the standalone correction;
- exact 2.3.16 dispatcher semantics and the legacy Documentation alias remain unchanged;
- `gwp-admin` is selected and registered at the supported `print_styles_array` boundary;
- page title, setting labels/descriptions, text input, select and save button resolve to Vazirmatn in the authentic standalone Settings document;
- textarea is checked when deterministically rendered and otherwise remains explicitly `NOT_PROVEN`;
- configured `exclude_selectors` remain authoritative, including a real `.vazir-gp-evidence-excluded` text surface and a non-excluded sibling;
- protected Dashicons / GFFontAwesome / FontAwesome computed families are preserved when actually rendered, or reported `NOT_EXERCISED` when the exact fixture renders none;
- bundled Vazirmatn WOFF2 requests occur, configured Loader weights match emitted `@font-face` rules, and duplicate font URL delivery is absent;
- `gwp-admin-css` remains the host stylesheet and no extra standalone Vazir `<link>` is introduced;
- the real Settings save interaction still persists text, select and checkbox values and follows the host notice/page lifecycle;
- no `fonts.googleapis.com` or `fonts.gstatic.com` request occurs on currently reachable normal-admin, Settings, or Documentation-alias surfaces.

The adapter reuses the existing `enable_admin` and `enable_gravity_forms` preferences and the existing `exclude_selectors` authority. Checkbox/radio glyphs and icon pseudo-elements are not treated as text typography.

Google Fonts markup remains source-only in the unreachable legacy Documentation implementation for exact 2.3.16 and is not described as a runtime defect. A later Gravity Perks release must be exercised independently before its compatibility is claimed.
