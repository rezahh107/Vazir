# GravityView 3.3.4 typography characterization

## Qualified boundary

This characterization is bound to the exact Owner-supplied runtime exercised by the Product Evidence Lab:

- GravityView `3.3.4` (`gravityview.zip`, `7,569,755` bytes, SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`);
- Gravity Forms prerequisite `3.1.1.1` (SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`).

Licensed package bytes remain outside the repository and uploaded public evidence. Source evidence records only bounded provenance such as relative paths, hashes, semantic-token presence, registered handles, and runtime identity.

Production admission is capability-based, but compatibility evidence is exact-version-bound. Other GravityView versions remain `NOT_PROVEN` until separately exercised.

## Qualification authority and repair scope

The qualification preceding PR #24 established four distinct facts:

1. Modern Vantage frontend typography was already correct and required no frontend repair.
2. The authentic Gutenberg React Select control/value used an explicit editor system stack while its combobox input already resolved to Vazirmatn.
3. The authentic View-block React Datepicker root/current-month/day used `"Helvetica Neue", helvetica, arial, sans-serif` while its associated input already resolved to Vazirmatn.
4. The detached React Select portal was `NOT_PROVEN`, while the GravityView oEmbed admin placeholder was a reproduced typography `FAIL` without an admitted production seam.

PR #24 repaired exactly the two normal-descendant editor failures. The final-boundary qualification documented below does not extend production typography behavior. It exists to decide the remaining portal and oEmbed boundaries from exact runtime evidence.

The repaired oEmbed qualification additionally recognizes the stable source-association attributes owned by WordPress `wp.mce.views`. This corrects the earlier evidence gap without admitting a production repair.

## Production lifecycle and ownership preserved from PR #24

`VazirFont_GravityView_Integration` remains a bounded editor-only adapter. It initializes when GravityView is present and attaches on WordPress' supported `enqueue_block_editor_assets` lifecycle.

At that lifecycle point it requires:

- registered block `gk-gravityview-blocks/view`;
- GravityView-owned editor-style handle `gk-gravityview-blocks-view-editor-style` in the block's `editor_style_handles` metadata;
- the same style handle registered in WordPress.

If any requirement is absent, the adapter fails closed and emits no repair CSS. When admitted, it uses:

`wp_add_inline_style( 'gk-gravityview-blocks-view-editor-style', ... )`

GravityView remains authoritative for block registration, controls, scripts, React Select/Datepicker behavior, editor state, host styles, icons, detached portals, and oEmbed rendering. WordPress `wp.mce.views` remains authoritative for Classic Editor wpview/source association. Vazir does not edit or fork GravityView or WordPress assets and adds no JavaScript typography mutation.

## PR #24 React Select repair remains runtime-proven

The exact admitted production selector remains:

```css
.gk-gravityview-blocks .view-selector [class$="-control"]
```

Exact GravityView `3.3.4` source itself uses the semantic `[class$="-control"]` suffix. Production does not depend on generated Emotion hashes.

Exact-runtime regression evidence after the final-boundary harness changes re-proves:

- control and selected/value text resolve to Vazirmatn;
- the combobox input, which is not directly repaired, still resolves to Vazirmatn;
- the real control opens, reports `aria-expanded="true"`, and closes through Escape with no visible listbox left behind;
- a descendant exclusion prevents the control/value repair from crossing the configured exclusion boundary;
- removal of that exclusion restores Vazirmatn.

The verification harness intentionally reacquires authentic React Select nodes across interaction/exclusion phases because React may rerender and replace internal input nodes. Node identity is not treated as a product contract.

No `!important` is used for the admitted React Select repair.

## PR #24 Datepicker repair remains runtime-proven

The exact admitted production selector remains:

```css
.gk-gravityview-blocks .react-datepicker
```

Exact-runtime regression evidence re-proves:

- Datepicker root, current month, and representative day text resolve to Vazirmatn;
- the associated input remains Vazirmatn without a direct input repair;
- descendant exclusion blocks inheritance of the admitted repair and removal restores it;
- selecting a real day updates the associated input and closes the Datepicker;
- reopening and pressing Escape closes it normally.

No broad `.react-datepicker` rule is emitted outside the GravityView block scope.

## Existing exclusion authority remains unchanged

`vazir_font_options['exclude_selectors']` remains the only exclusion authority. The admitted normal-descendant GravityView repairs continue through `VazirFont_Selector_Boundary` and retain negative applicability plus descendant-containment protection.

No GravityView-specific persisted exclusion model, JavaScript selector engine, response rewrite, renderer replacement, or DOM mutation was added by the final-boundary qualification.

## Detached React Select portal — final disposition: `NOT_PROVEN`

### Authentic interaction attempts

The exact `gk-gravityview-blocks/view` Gutenberg fixture was exercised through bounded user-level interactions. The closure probe attempted:

1. focus plus `ArrowDown` on the authentic combobox;
2. click on the authentic React Select control;
3. input click plus `ArrowDown` when the live input was available.

The earlier characterization on the same exact runtime observed a top-level `document.body` portal candidate whose class was an Emotion-generated `gk-select-*` value, but it exposed no stable visible ARIA listbox or text-bearing option. The final bounded probe likewise did not obtain a stable visible `role=listbox` / `role=option` surface.

No React internals were manipulated and no portal markup was manufactured. Escalating beyond these bounded semantic interactions would make the evidence increasingly dependent on transient implementation internals rather than a trustworthy user path.

### Typography result

Because no stable visible text-bearing menu/listbox/option was reached, portal typography could not be measured from actual rendered option text. Source presence alone is not promoted to a runtime typography result.

Therefore the exact final portal typography disposition remains:

`NOT_PROVEN`

### Ownership / association result

Exact source still proves that `DocumentAwareSelect` portals the menu to the relevant document body, outside `.view-selector` ancestry. Runtime evidence does **not** establish a stable visible association suitable for production targeting:

- generated Emotion hashes are not authority;
- the opaque `gk-select-*` candidate alone is not sufficient proof of a unique supported GravityView ownership contract;
- no stable visible listbox/option IDs or ARIA linkage were captured that can serve as a production selector contract;
- generic `[role=listbox]` or generic React Select rules would affect unrelated admin controls.

The source-control-to-portal association required for exclusion semantics therefore also remains `NOT_PROVEN`. No portal repair seam is admitted, and the existing single exclusion authority is not weakened or duplicated.

This is intentionally **not** upgraded to `NO_ADMISSION`: the runtime did not expose enough stable visible portal structure to prove that every safe supported association is impossible. The truthful boundary is lack of proof, not proof of impossibility.

## GravityView oEmbed placeholder — final disposition: `NOT_PROVEN`

### Authentic supported insertion path

The qualification does not treat a manually mounted AJAX response as production scope authority. It creates a test-only Classic Editor post type and persists the real GravityView entry URL as a native `[embed]...[/embed]` shortcode.

The exercised path is:

`WordPress Classic Editor -> TinyMCE wp.mce.views embed preview -> authenticated admin-ajax.php action=parse-embed -> GravityView oEmbed response`

The runtime observed a real successful `parse-embed` POST containing the exact GravityView entry URL, an HTTP 200 successful response whose body contains GravityView's `.loading-placeholder`, and authentic insertion of that placeholder through WordPress' native wpview/TinyMCE machinery.

### Stable WordPress wpview/source association — proven

WordPress 7.1 `wp.mce.views` is authoritative for the relationship between the persisted source text and the rendered wpview node. The authentic rendered ancestry contains:

```text
.loading-placeholder
└─ .wpview.wpview-wrap
   ├─ data-wpview-type="embed"
   └─ data-wpview-text="<encodeURIComponent(exact persisted source)>"
      └─ body#tinymce
```

For the exact runtime fixture, the persisted Classic Editor source is the exact GravityView entry shortcode. The qualification mechanically proves all of the following:

- the Classic Editor textarea source equals the persisted fixture source;
- `data-wpview-type` is exactly `embed`;
- `data-wpview-text` equals `encodeURIComponent()` of that exact persisted source;
- `decodeURIComponent(data-wpview-text)` equals the persisted source;
- WordPress `wp.mce.views.getText()` returns the same exact persisted source;
- WordPress `wp.mce.views.getInstance()` binds the node to an instance whose `text` is the same exact persisted source;
- that source contains the exact GravityView entry URL used by the successful `parse-embed` request.

The association classifier also has deterministic negative controls. It must reject an unrelated expected source, a missing `data-wpview-text`, a missing `data-wpview-type`, a replaced unrelated encoded source, and a replaced unrelated view type. The exact runtime rejects all of those controls. Therefore the two wpview data attributes are material evidence rather than ignored metadata.

There is still no GravityView-specific class or id ancestor around the placeholder. However, the exact WordPress-owned wpview source attributes provide a stable source-bound presentation association for this exact persisted GravityView embed. The earlier conclusion that the rendered context exposed no stable relevant attribute was therefore too strong.

### Typography and cascade — failure and `!important` requirement proven

The authentic placeholder remains a real typography failure:

- the placeholder/root context resolves to the TinyMCE/WordPress serif stack;
- the text-bearing heading and paragraph resolve to the system stack declared inline by exact GravityView `3.3.4`;
- the exact rendered heading and paragraph both contain inline `font-family` declarations.

A normal stylesheet declaration cannot defeat those inline `font-family` declarations. A CSS correction would require higher importance, in practice `!important`.

This fact does not by itself admit a production repair; the remaining ownership/isolation/exclusion conditions must also hold.

### Isolation from unrelated embeds — exact-source association proven

The wpview classifier is bound to both the exact encoded source and `data-wpview-type="embed"`. Its negative controls reject an unrelated embed source as well as removed or replaced relevant attributes. This proves that the qualification can distinguish the exact GravityView fixture source from an unrelated wpview source without using generic `.wpview` or `.loading-placeholder` identity alone.

This is an exact-source association result. It does not create a new persisted selector language or imply that every arbitrary GravityView embed can be targeted by broad generic WordPress embed selectors.

### Existing exclusion authority — preservation remains `NOT_PROVEN`

The configured `vazir_font_options['exclude_selectors']` remains the single exclusion authority. The exact persisted oEmbed fixture source is only the native GravityView `[embed]...[/embed]` shortcode; it is **not** itself placed inside the configured `.vazir-gv-evidence-excluded` boundary.

Consequently, these observed facts:

- the placeholder is not a descendant of `.vazir-gv-evidence-excluded` inside the TinyMCE frame; and
- the containing iframe is not a descendant of that selector in the outer document

do **not** prove that WordPress cannot preserve or expose an association when the source actually belongs to an excluded subtree. The current fixture simply does not exercise that required condition.

The stable `data-wpview-text` association proves source identity, but source identity alone is not proof of the arbitrary CSS-selector ancestry/state represented by the existing `exclude_selectors` contract. The qualification does not invent a second exclusion model, reinterpret selectors through shortcode text, or mutate/render a synthetic exclusion association.

Therefore exclusion preservation for this detached wpview preview remains:

`NOT_PROVEN`

### Resulting admission decision

The repaired evidence invalidates the prior `NO_ADMISSION` conclusion because a relevant supported WordPress association had not been evaluated. The exact runtime now proves:

- authentic GravityView oEmbed typography failure;
- stable source-bound WordPress wpview presentation association;
- deterministic rejection of unrelated/missing/replaced wpview association evidence;
- inline cascade requiring `!important` for a CSS correction;
- but **not** preservation of the existing single `exclude_selectors` authority, because the persisted fixture does not exercise an excluded source.

The final oEmbed disposition is therefore:

`NOT_PROVEN`

No production repair is admitted from this result. `ADMITTABLE_REPAIR_SEAM` would require evidence that the existing exclusion authority is preserved without response rewriting, vendor edits, renderer replacement, DOM mutation, or a second exclusion model. `NO_ADMISSION` would require mechanically rejecting the relevant supported association evidence as well; this repaired runtime evidence does not do that.

## Icon and glyph ownership

The final-boundary work changes no production selector and adds no icon rules. Exact-runtime regression evidence continues to show:

- representative GravityView icon pseudo-elements retain the `gravityview` icon family;
- WordPress Dashicons retain `dashicons`;
- Gravity Forms icon ownership is not inferred when the exact GravityView path does not render a representative Gravity Forms icon node.

## Frontend remains untouched and correct

Modern Vantage frontend remains `ALREADY_CORRECT`. Exact runtime continues to show the real root, table header, entry values, search controls, pagination when rendered, filtered state, and the configured exclusion fixture behaving correctly under the existing Vazirmatn delivery.

No frontend theme-token override, per-View override, or new frontend dependency is introduced.

## Exact GravityView 3.3.4 disposition matrix

| Surface | Final state | Evidence meaning |
| --- | --- | --- |
| Modern Vantage frontend | `ALREADY_CORRECT` | Representative real inner text resolves to Vazirmatn; no frontend repair required. |
| React Select control/value | `REPAIRED / RUNTIME_PROVEN` | PR #24 repair remains green; semantic control selector only. |
| React Select input | `ALREADY_CORRECT` | Real combobox input resolves to Vazirmatn without a direct repair. |
| React Select detached portal | `NOT_PROVEN` | No stable visible listbox/option typography surface reached after bounded authentic interaction; no repair admitted. |
| React Datepicker | `REPAIRED / RUNTIME_PROVEN` | PR #24 root repair remains green; input already correct and interactions work. |
| GravityView oEmbed placeholder | `NOT_PROVEN` | Authentic failure and exact source-bound wpview presentation association are proven, and inline font requires `!important`; preservation of the existing exclusion authority is not proven. |
| GravityView icon family | `PRESERVED` | Representative GravityView glyph retains the `gravityview` family. |
| WordPress Dashicons | `PRESERVED` | Dashicons retain `dashicons`. |
| Normal-descendant exclusions | `RUNTIME_PROVEN` | React Select and Datepicker repairs remain bounded by the single existing exclusion authority. |
| Detached portal exclusion association | `NOT_PROVEN` | Stable source-control-to-portal association was not established. |
| oEmbed wpview/source association | `RUNTIME_PROVEN` | `data-wpview-text`, `data-wpview-type`, `wp.mce.views.getText()`, and `getInstance()` bind the authentic wpview to the exact persisted GravityView source; negative controls reject unrelated evidence. |
| oEmbed exclusion preservation | `NOT_PROVEN` | The persisted oEmbed fixture does not exercise an excluded source, so the existing `exclude_selectors` guarantee cannot yet be verified for the detached preview. |

## Evidence contract

The GravityView Product Evidence profile now has distinct responsibilities:

1. baseline characterization of frontend, authentic editor assets, React Select, Datepicker, icons, and authenticated oEmbed failure;
2. PR #24 production-repair regression verification;
3. bounded detached-portal closure characterization;
4. authentic Classic Editor/TinyMCE oEmbed insertion characterization, including WordPress wpview data-attribute/source association, negative association controls, exact lifecycle/source probes, cascade evidence, unrelated-source isolation, and fail-honest exclusion-preservation classification.

The profile remains successful when it truthfully records an unresolved product disposition such as portal `NOT_PROVEN` or oEmbed `NOT_PROVEN`; a green evidence harness does not mean every host-owned surface is converted to Vazirmatn or that a production repair has been admitted.

## Closure state

GravityView exact `3.3.4` is **not yet eligible for destination closure** under a destination that requires every remaining boundary to have a final supported disposition other than unresolved `NOT_PROVEN`.

The detached React Select portal remains unresolved because a stable visible menu surface and source-control/exclusion association have not been established.

The oEmbed placeholder also remains unresolved. Its stable exact-source wpview presentation association is now proven, but the current fixture does not prove preservation of the single existing `exclude_selectors` authority for an excluded source. That boundary must remain `NOT_PROVEN` unless future bounded evidence resolves the exclusion question without changing the locked ownership model.

Neither unresolved boundary justifies speculative production repair. No tag, GitHub Release, publication, deployment, or production behavior change belongs to this final-boundary qualification batch.
