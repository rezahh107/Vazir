# GravityView 3.3.4 typography characterization

## Qualified boundary

This characterization is bound to the exact Owner-supplied runtime exercised by the Product Evidence Lab:

- GravityView `3.3.4` (`gravityview.zip`, `7,569,755` bytes, SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`);
- Gravity Forms prerequisite `3.1.1.1` (SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`).

Licensed package bytes remain outside the repository and uploaded public evidence. Source evidence records only bounded provenance such as relative paths, hashes, semantic-token presence, registered handles, and runtime identity.

Production admission is capability-based, but compatibility evidence is exact-version-bound. Other GravityView versions remain `NOT_PROVEN` until separately exercised.

## Qualification authority and repair scope

The qualification preceding this repair established four distinct facts:

1. Modern Vantage frontend typography was already correct and required no frontend repair.
2. The authentic Gutenberg React Select control/value used an explicit editor system stack while its combobox input already resolved to Vazirmatn.
3. The authentic View-block React Datepicker root/current-month/day used `"Helvetica Neue", helvetica, arial, sans-serif` while its associated input already resolved to Vazirmatn.
4. The detached React Select menu portal remained `NOT_PROVEN`, while the oEmbed admin placeholder was a reproduced `FAIL` with no admitted safe production scope.

This production batch therefore admits exactly the two normal-descendant editor repairs and nothing else.

## Production lifecycle and ownership

`VazirFont_GravityView_Integration` is a bounded editor-only adapter. It initializes when GravityView is present and attaches on WordPress' supported:

`enqueue_block_editor_assets`

At that lifecycle point it requires all of the following host capabilities:

- registered block `gk-gravityview-blocks/view`;
- GravityView-owned editor-style handle `gk-gravityview-blocks-view-editor-style` present in the block's `editor_style_handles` metadata;
- the same style handle registered in WordPress.

If any requirement is absent, the adapter fails closed and emits no repair CSS. When admitted, it uses:

`wp_add_inline_style( 'gk-gravityview-blocks-view-editor-style', ... )`

GravityView remains authoritative for block registration, controls, scripts, React Select/Datepicker behavior, editor state, host styles, icons, detached portals, and oEmbed rendering. Vazir does not edit or fork GravityView assets and adds no JavaScript typography mutation.

## Admitted React Select repair

The exact admitted production selector is:

```css
.gk-gravityview-blocks .view-selector [class$="-control"]
```

This does not depend on a generated Emotion hash prefix. Exact GravityView `3.3.4` source itself uses the semantic `[class$="-control"]` suffix to locate the real React Select control, so the suffix is host-owned semantic evidence rather than an inferred generated hash.

The repair sets only `font-family` on that control. The selected/value text inherits from the repaired control. The real `input[role="combobox"]` is intentionally not directly targeted because qualification already showed that input resolving to Vazirmatn.

No `!important` is admitted. The bounded selector has sufficient specificity to override the host control stack at the qualified cascade point without escalating importance.

## Admitted Datepicker repair

The exact admitted production selector is:

```css
.gk-gravityview-blocks .react-datepicker
```

Exact GravityView `3.3.4` declares its Helvetica/Arial stack on the Datepicker root. The production repair therefore corrects that root only; current-month and day text inherit the repaired family. The associated input is not directly targeted because it was already correct.

No broad `.react-datepicker` rule is emitted outside the GravityView block scope, and no descendant-wide reset is added.

## Exclusion semantics

`vazir_font_options['exclude_selectors']` remains the only exclusion authority. The GravityView adapter reuses `VazirFont_Selector_Boundary` and the same negative-applicability model as other inheritable Gravity adapters:

- an excluded target or descendant is not repaired;
- an admitted target containing an excluded subtree is also not repaired, preventing Vazirmatn inheritance from crossing into the excluded subtree;
- `.gk-gravityview-blocks <local selector>` can be safely relativized only because the production targets are guaranteed inside that exact scope;
- unsafe top-level document relationships and real element-level `:has()` exclusions fail the bounded repair closed;
- pseudo-element exclusions stay outside relational text guards and remain host/icon-owned.

The exact-runtime browser profile additionally places a real exclusion class inside the React Select control/value and Datepicker subtrees, verifies that the ancestor repair no longer applies, then removes the class and verifies that non-excluded behavior returns. Deterministic contracts separately cover unsafe relationship fail-closed behavior.

## Icon and glyph ownership

The repair contains only element-level `font-family` rules for the two admitted text roots. It does not target icon pseudo-elements.

The existing exact-runtime profile continues to verify:

- representative GravityView icon pseudo-elements retain the `gravityview` icon family;
- WordPress Dashicons retain `dashicons`;
- any Gravity Forms icon family that renders on the exact path must not be replaced by Vazirmatn; when no representative node renders, that surface remains `NOT_PROVEN` rather than inferred.

## Frontend remains untouched

Modern Vantage frontend typography remains an `ALREADY_VAZIRMATN` regression surface. The real root, table header, entry value, search label/input/button, pagination when rendered, and filtered state inherit Vazirmatn correctly.

No frontend theme-token override, per-View override, or new frontend dependency is introduced by this repair.

## Detached React Select menu remains NOT_PROVEN

Exact installed source portals the menu to the relevant document body. The qualification observed a real `gk-select` portal candidate, but did not establish stable visible listbox/option typography suitable for production admission.

The portal remains outside this repair. In particular, production code does not:

- target generated Emotion hash classes;
- apply a global React Select rule in wp-admin;
- pretend the portal is a `.view-selector` descendant;
- use JavaScript DOM mutation to associate source control and detached portal.

A future admission requires its own stable GravityView-owned association and exclusion model.

## oEmbed remains unresolved

The profile continues to exercise GravityView oEmbed through WordPress' authenticated `admin-ajax.php` `parse-embed` route with a real GravityView entry URL.

Exact `3.3.4` emits a generic `.loading-placeholder` fragment whose heading and paragraph carry inline system-font declarations. The runtime failure is real, but the returned fragment provides no stable GravityView-specific wrapper. A normal stylesheet cannot beat the inline declarations, while a broad `.loading-placeholder !important` rule would exceed product ownership.

Therefore oEmbed remains `FAIL / UNREPAIRED` in this production batch. Vazir does not rewrite GravityView HTML or vendor source.

## Evidence contract

The GravityView Product Evidence profile intentionally has two phases:

1. the existing qualification characterization continues to exercise frontend behavior, authentic editor assets, React Select portal reachability, Datepicker reachability, icon ownership, and authenticated oEmbed;
2. `production-repair-characterization.mjs` then verifies the admitted production behavior on the same exact runtime.

The repair-verification phase requires:

- React Select control → Vazirmatn;
- React Select selected/value text → Vazirmatn;
- React Select input remains Vazirmatn;
- real control open/close interaction remains functional;
- React Select descendant exclusion blocks ancestor repair and non-excluded behavior recovers;
- Datepicker root/current month/day → Vazirmatn;
- Datepicker input remains Vazirmatn;
- real open/select/update/reopen/Escape-close behavior remains functional;
- Datepicker descendant exclusion blocks ancestor repair and non-excluded behavior recovers;
- frontend remains `ALREADY_VAZIRMATN`;
- detached portal remains `NOT_PROVEN` unless separately and independently qualified;
- oEmbed remains an executed unrepaired failure without causing the evidence harness itself to falsely fail.

Production ZIP dry-run qualification reuses the same profile against the already-built package, so packaged-artifact evidence—not source-tree evidence alone—must prove the two admitted surfaces before a later release can rely on this repair.

## Release boundary and remaining gaps

This repair is post-`v1.4.0`; it does not modify or republish the already released `v1.4.0`. Source product version remains unchanged in this batch because no new release is created here.

GravityView is **not CLOSED** after these two repairs. Remaining gaps include at least:

- detached React Select portal association/typography: `NOT_PROVEN`;
- oEmbed placeholder typography: reproduced `FAIL`, intentionally unrepaired;
- any GravityView version other than exact `3.3.4`: `NOT_PROVEN` until separately exercised.

No tag, GitHub Release, publication, or deployment belongs to this characterization/repair PR.
