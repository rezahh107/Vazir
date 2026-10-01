# Vazir Font for WordPress

A self-hosted Persian typography plugin for WordPress with optional Gravity Forms, Gravity Flow, Gravity Perks, and bounded GravityView editor compatibility.

## Release 1.5.0

`v1.5.0` is a backward-compatible feature/evidence release prepared over the currently published `v1.4.0`. It keeps the official self-hosted Vazirmatn `v33.003` payload and the existing WordPress/Gravity compatibility architecture while adding the post-1.4.0 work that is ready for production release:

- bounded GravityView View-block editor typography support for the exact admitted React Select control/value and normal-descendant React Datepicker surfaces, attached through GravityView's own registered editor-style handle;
- exact runtime closure for GP Advanced Select `1.1.21` and GP File Upload Pro `1.5.13`, both resolving through native Vazirmatn inheritance with **no add-on-specific production CSS/PHP/JS repair**;
- expanded exact-artifact Product Evidence coverage across eight retained profiles: `gravityforms`, `gravityflow`, `gravityview`, `gravityperks`, `gp-advanced-select`, `gp-file-upload-pro`, `gravity-stack`, and `gravity-addons-stack`, including representative combined-stack coexistence evidence;
- continued capability-based, version-neutral production admission for Gravity integrations: exact product/add-on versions define evidence boundaries, not runtime activation whitelists.

The persisted option/API contract is unchanged. Product version `1.5.0` remains intentionally separate from the unchanged persisted option schema version `1.3.0`, so this release bump alone does not run an options migration.

Evidence claims remain bounded to what was actually exercised. Later/unexecuted Gravity ecosystem versions remain `NOT_PROVEN`, not automatically unsupported. GravityView's detached React Select portal remains `NOT_PROVEN`, and the generic oEmbed `.loading-placeholder` inline-font failure remains intentionally unrepaired because no stable GravityView-owned insertion scope has been admitted. The `gravity-stack` and `gravity-addons-stack` profiles are representative coexistence evidence rather than exhaustive compatibility guarantees.

## Intended runtime coverage

The plugin is designed to cover:

- WordPress frontend in classic and block themes;
- wp-admin;
- the login screen;
- Block Editor and Site Editor content canvases through `enqueue_block_assets`;
- Gravity Forms frontend, Preview, Form Editor, and No Conflict Mode through registered WordPress style handles;
- currently supported Gravity Forms legacy/current wrapper markup;
- Gravity Flow Inbox typography through the product's supported admin/frontend enqueue seams, including the AG Grid text root, material text/date inputs, and Flow-bound Flatpickr calendar without replacing host-owned icon families or Inbox behavior;
- Gravity Perks standalone Perk Settings typography through WordPress' `print_styles_array` boundary while the host `gwp-admin` stylesheet is being processed;
- Gravity Perks frontend add-ons that preserve normal Gravity Forms/Vazir inheritance; exact current evidence covers GP Advanced Select `1.1.21` and GP File Upload Pro `1.5.13` without add-on-specific production repair;
- the two exact-runtime-admitted GravityView View-block editor surfaces: React Select control/value typography and the normal-descendant React Datepicker, through GravityView's own registered editor-style handle.

Automated PHP and repository contracts verify the loading/API paths. Real-WordPress smoke lanes verify bootstrap and enqueue behavior, and Chromium computed-style lanes exercise WordPress frontend/login/admin/editor coverage on classic and block themes. Licensed Gravity product/add-on coverage is handled separately by the Product-Wide Reproducible Evidence Lab; see `docs/CHARACTERIZATION.md` and `docs/GRAVITY-PERKS-FRONTEND-CHARACTERIZATION.md`.

## Font delivery

The bundled typeface is the official upstream **Vazirmatn v33.003** release from `rastikerdar/vazirmatn`, pinned to release commit `83629f877e8f084cc07b47030b5d3a0ff06c76ec`. The plugin self-hosts the static WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`; it does not fetch font bytes at runtime.

`assets/fonts/Vazirmatn-PROVENANCE.md` records the exact upstream release archive identity, source paths, byte sizes, SHA-256 digests, license, and author material used to reproduce the bundled files.

Static delivery remains intentional. The product already exposes five discrete weight selections, while the upstream variable webfont is 111,152 bytes and each selected static face is approximately 50–51 KiB. Static faces preserve the existing settings model and let the browser request only weights actually used by a page. Variable delivery would become advantageous only when enough distinct weights are consumed on the same surface to outweigh its larger single request and the additional migration/verification complexity.

The runtime:

- references only packaged `vazirmatn-*.woff2` files;
- exposes the truthful canonical CSS family `Vazirmatn`;
- retains the public `vazir_font_family` filter as the existing compatibility API for overriding the complete family stack;
- does **not** create a hidden `Vazir` alias for Vazirmatn bytes;
- uses `font-display: swap`;
- performs no default font preloading;
- has no CDN dependency.

Existing callbacks on `vazir_font_family` continue to run unchanged. A callback that deliberately returns the legacy `Vazir` family name remains responsible for providing that family itself; the plugin no longer bundles legacy Vazir binaries under that identity.

### Upgrade and rollback

The persisted option name and schema are unchanged: existing frontend/admin/Gravity compatibility toggles, selected weights, and `exclude_selectors` remain backward compatible. `enable_gravity_forms` remains the stored Gravity-specific compatibility key and is reused by the Gravity Forms, Gravity Flow, Gravity Perks, and bounded GravityView adapters. Flow additionally respects the existing frontend/admin context toggle for the surface being rendered, while the standalone Perks Settings and GravityView editor adapters require admin typography to be enabled.

The plugin keeps the product release version and persisted schema version as separate authorities. `VAZIR_FONT_VERSION` is `1.5.0`, while the unchanged persisted schema remains `VAZIR_FONT_SCHEMA_VERSION = 1.3.0`; the bounded GravityView adapter and evidence closure do not require an option migration.

For this personal plugin, rollback is intentionally simple: reinstall/restore the previous compatible plugin revision/package. Because the option schema is unchanged, the prior version can reuse the same saved settings.

## Gravity Forms compatibility

The adapter uses current Gravity Forms APIs for stylesheet delivery:

- `gform_enqueue_scripts`;
- `gform_preview_styles`;
- `gform_noconflict_styles`.

`gform_field_content`, `gform_field_css_class`, and narrowly scoped Gravity Forms `!important` rules remain compatibility mechanisms until licensed browser characterization proves equivalent rendering without them. They are not treated as permanently required.

The exact qualified runtime for the current closure evidence is Gravity Forms `3.1.1.1`. Additional direct selectors beyond the existing Theme Framework/current/Legacy form rules are admitted only from rendered exact-runtime failures rather than source similarity.

The plugin does not flush `GFCache`, delete Gravity Forms-generated CSS, delete Gravity Forms transients, or schedule periodic Gravity Forms/font cleanup.

## Gravity Flow compatibility

`VazirFont_GravityFlow_Integration` uses capability-based runtime admission. Runtime admission is not version-gated: when the Gravity Flow runtime is present, the adapter attaches only to `gravityflow_enqueue_admin_scripts` and `gravityflow_enqueue_frontend_scripts`. At the actual enqueue boundary it requires the corresponding host stylesheet handle (`gravityflow_admin_css` or `gravityflow_theme_css`) to be registered. If that capability is absent, the adapter fails closed for that surface rather than applying fallback/global CSS. There is no accepted-version whitelist.

The adapter emits only scoped typography CSS; it adds no JavaScript and does not mutate or replace Inbox data, assignment, authorization, workflow state, search, pagination, filtering, navigation, AG Grid lifecycle, or icon rendering. Its selectors remain narrowly bounded to the AG Grid theme root, material AG text/date inputs, and Flow-owned Flatpickr portal. If a later runtime no longer renders one of those selectors, that rule naturally becomes a no-op.

Real browser/runtime qualification currently covers Gravity Flow `3.1.0`. That qualified package explicitly declares a system stack on `.gflow-grid .ag-theme-alpine`, AG Grid text/date inputs, and the Flow-bound Flatpickr calendar, and the licensed evidence lab verifies the resulting repair and protected icon/exclusion behavior on that exact runtime. A synthetic alternate-version contract proves only that production admission is not controlled by the version string; it is not compatibility proof for that synthetic or any unexecuted Gravity Flow version.

## Gravity Perks compatibility

`VazirFont_GravityPerks_Integration` uses capability-based admission for the standalone Perk Settings document. It attaches only during the supported WordPress `print_styles_array` pass when Gravity Perks has selected and registered its `gwp-admin` stylesheet, returns the host style-handle list unchanged, and adds bounded typography with `wp_add_inline_style( 'gwp-admin', ... )`.

Gravity Perks remains authoritative for routing, Settings rendering, saving, controls, notices, scripts, and host styles. Vazir reuses Loader-owned font delivery and the existing shared settings/exclusion authorities; it does not rewrite the document, replace host assets, or add a JavaScript typography engine.

Real browser/runtime qualification currently covers exact Gravity Perks `2.3.16`. Other versions remain `NOT_PROVEN` until separately exercised. On the exact qualified runtime, the generated Documentation URL aliases to the Settings handler and is not claimed as independent Documentation-page compatibility evidence.

Frontend Perk qualification is separate from the standalone Settings adapter. Exact GP Advanced Select `1.1.21` resolves its exercised Tom Select control, search/value/options/no-results/multiselect surfaces and rebuilt widget through native Vazirmatn inheritance. Exact GP File Upload Pro `1.5.13` resolves its exercised uploader guidance/action/error/file metadata plus detached crop actions and rerendered state through native inheritance. Both dedicated profiles also prove single-authority bundled font delivery with no duplicate URL requests. No production adapter was added for either add-on; later versions remain `NOT_PROVEN` until separately exercised, and their version strings must not become production admission gates.

The separate `gravity-addons-stack` profile verifies representative coexistence with the retained Gravity stack and both add-ons enabled together. It is not exhaustive cross-product compatibility. See `docs/GRAVITY-PERKS-FRONTEND-CHARACTERIZATION.md` for exact package identities, exercised interactions, and explicit `NOT_PROVEN` / `NOT_REACHABLE` boundaries.

## GravityView compatibility boundary

`VazirFont_GravityView_Integration` is intentionally a small View-block **editor-only** adapter, not a general GravityView presentation layer. Production admission is capability-based: the GravityView runtime must be present, the real `gk-gravityview-blocks/view` block must be registered, its editor-style metadata must contain `gk-gravityview-blocks-view-editor-style`, and that host handle must be registered at `enqueue_block_editor_assets`. Only then does Vazir attach bounded inline CSS to that GravityView-owned handle.

The admitted selectors are deliberately small:

- `.gk-gravityview-blocks .view-selector [class$="-control"]` for the React Select control/value inheritance boundary. Exact GravityView `3.3.4` itself uses the semantic `-control` suffix to locate the real control; generated Emotion hash prefixes are not used as production selector authority. The combobox input already resolves to Vazirmatn and is not directly targeted.
- `.gk-gravityview-blocks .react-datepicker` for the normal-descendant View-block Datepicker. Current-month and day text inherit from this repaired root instead of receiving broad descendant overrides.

Both rules reuse the existing `enable_admin`, `enable_gravity_forms`, `vazir_font_family`, and `exclude_selectors` authorities plus `VazirFont_Selector_Boundary`. Unsafe document-context exclusions fail the bounded repair closed. No JavaScript typography mutation, vendor edit, replacement stylesheet, frontend theme-token override, or `!important` is part of this admission.

The modern Vantage frontend remains already-correct and receives no new repair. The detached React Select menu portal remains `NOT_PROVEN` and is not treated as a `.view-selector` descendant. GravityView's generic oEmbed `.loading-placeholder` heading/paragraph inline-font failure remains intentionally unrepaired because no stable GravityView-specific insertion scope has been admitted. GravityView icon ownership and WordPress Dashicons remain host-owned. See `docs/GRAVITYVIEW-CHARACTERIZATION.md` for the exact evidence boundary.

Production admission is capability-based, while compatibility evidence is exact-version-bound. Current licensed browser/runtime evidence targets GravityView `3.3.4` with Gravity Forms `3.1.1.1`; other GravityView versions remain `NOT_PROVEN` until separately exercised. GravityView is not generally CLOSED by these two repairs.

## Requirements and PHP policy

- Minimum WordPress: 6.7
- Minimum PHP: 7.4
- Gravity Forms: optional; current exact browser/runtime qualification covers `3.1.1.1`
- Gravity Flow: optional; runtime admission is capability-based; current exact browser/runtime qualification covers `3.1.0`
- Gravity Perks: optional; standalone Settings admission is capability-based; current exact browser/runtime qualification covers `2.3.16`
- GP Advanced Select: optional evidence-qualified frontend add-on; current exact browser/runtime qualification covers `1.1.21` with native inheritance and no production repair
- GP File Upload Pro: optional evidence-qualified frontend add-on; current exact browser/runtime qualification covers `1.5.13` with native inheritance and no production repair
- GravityView: optional; bounded View-block editor admission is capability-based; current exact browser/runtime qualification targets `3.3.4`
- Recommended production PHP when Gravity Forms is part of the stack: 8.3, matching current Gravity Forms guidance
- For WordPress-only deployments, current WordPress hosting guidance recommends PHP 8.4 or later
- Latest PHP exercised by this repository CI: 8.5

PHP 8.5 testing by Gravity Forms core and official add-ons is currently documented as pending, so the CI lane is a forward-compatibility check and not a claim that every Gravity Forms deployment should run PHP 8.5.

## Settings

The existing option schema is preserved:

- `enable_frontend`;
- `enable_admin`;
- `enable_gravity_forms`;
- `font_weights`;
- `exclude_selectors`.

`enable_gravity_forms` is retained for stored-option compatibility and acts as the shared Gravity compatibility gate for the Gravity Forms, Gravity Flow, Gravity Perks, and bounded GravityView adapters. Gravity Flow also requires the corresponding `enable_frontend` or `enable_admin` context to be enabled; standalone Gravity Perks Settings and GravityView editor repair additionally require `enable_admin`.

`exclude_selectors` means that Vazirmatn `font-family` enforcement must not target matching element roots or their descendants. The runtime implements this as a negative selector boundary; it does not emit competing `font-family` reset declarations for generic element exclusions. Gravity Forms, Flow, Perks, and View normal-descendant qualification share `VazirFont_Selector_Boundary`, which treats quoted strings, attributes, and bounded functional selectors lexically, fails unsafe relationships and real element-level `:has()` closed, and does not depend on `ctype_*`.

## Development

```bash
composer install
composer test
composer lint
composer compat
```

`tests/runtime-contract.php` is the standalone core/Gravity Forms contract harness. `tests/version-schema-contract.php` proves that a product release bump does not trigger an options migration when the persisted schema is already current, while older schema state still follows the real migration path. `tests/gravity-version-neutrality-contract.php` guards the production Gravity bootstrap/adapters against evidence-only product/Perk version whitelists. `tests/gravityflow-runtime-contract.php`, `tests/gravityperks-runtime-contract.php`, and `tests/gravityview-runtime-contract.php` verify their respective capability/admission, settings, exclusion, ownership, and fail-closed boundaries. `tests/gravity-descendant-exclusion-contract.php` exercises the shared target-relative exclusion rules across admitted Gravity adapters. `tests/wordpress-smoke.php` is executed by CI against real WordPress installations. `tests/browser-characterization.mjs` verifies computed typography and icon behavior for current WordPress fixtures.

The Product-Wide Reproducible Evidence Lab adds separately diagnosable licensed profiles for Gravity Forms, Gravity Flow, GravityView, Gravity Perks, GP Advanced Select, GP File Upload Pro, the retained Gravity stack, and the full add-on coexistence stack. The add-on profiles use exact licensed package identities only as evidence boundaries and do not version-gate production admission. The GravityView profile preserves its qualification phase for frontend/portal/oEmbed/icon truth while a second repair-verification phase proves the two admitted editor surfaces, their real interactions, and exclusion behavior. The existing WordPress lanes remain the `wordpress` profile authority. A PASS is scoped to the exact profile/scenarios that ran; package verification or another profile is not a substitute for licensed runtime evidence.

## Licensing

Plugin code is GPL-2.0-or-later. Bundled Vazirmatn font files are distributed with the exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt`; reproducible source identity and SHA-256 digests are recorded in `assets/fonts/Vazirmatn-PROVENANCE.md`.