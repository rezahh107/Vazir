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

## Production lifecycle and ownership preserved from PR #24

`VazirFont_GravityView_Integration` remains a bounded editor-only adapter. It initializes when GravityView is present and attaches on WordPress' supported `enqueue_block_editor_assets` lifecycle.

At that lifecycle point it requires:

- registered block `gk-gravityview-blocks/view`;
- GravityView-owned editor-style handle `gk-gravityview-blocks-view-editor-style` in the block's `editor_style_handles` metadata;
- the same style handle registered in WordPress.

If any requirement is absent, the adapter fails closed and emits no repair CSS. When admitted, it uses:

`wp_add_inline_style( 'gk-gravityview-blocks-view-editor-style', ... )`

GravityView remains authoritative for block registration, controls, scripts, React Select/Datepicker behavior, editor state, host styles, icons, detached portals, and oEmbed rendering. Vazir does not edit or fork GravityView assets and adds no JavaScript typography mutation.

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

No GravityView-specific persisted exclusion model, JavaScript selector engine, response rewrite, or DOM mutation was added by the final-boundary qualification.

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

## GravityView oEmbed placeholder — final disposition: `NO_ADMISSION`

### Authentic supported insertion path

The final qualification does not treat a manually mounted AJAX response as production scope authority. It creates a test-only classic-editor post type and persists the real GravityView entry URL as a native `[embed]...[/embed]` shortcode.

The exercised path is:

`WordPress Classic Editor -> TinyMCE wp.mce.views embed preview -> authenticated admin-ajax.php action=parse-embed -> GravityView oEmbed response`

The runtime observed a real successful `parse-embed` request containing the GravityView entry URL and a returned body containing GravityView's `.loading-placeholder`. The placeholder was then inserted through WordPress' native wpview/TinyMCE machinery.

### Actual insertion context

Inside the TinyMCE content frame the relevant ancestry is effectively:

```text
.loading-placeholder
└─ .wpview.wpview-wrap
   └─ body#tinymce
```

The containing outer document exposes WordPress/TinyMCE editor wrappers such as `.mce-*`, `.wp-editor-container`, and `.wp-editor-wrap`.

No stable GravityView-specific ancestor, class, or attribute surrounds the inserted placeholder. The observed scope is generic WordPress embed/editor presentation scope shared with unrelated embed providers.

### Typography and cascade

The authentic placeholder remains a real typography failure:

- placeholder/root context does not provide a GravityView-owned typography scope;
- heading and paragraph resolve to the system stack declared inline by exact GravityView `3.3.4`;
- exact source confirms the generic `.loading-placeholder` plus inline `font-family` declarations on the text-bearing heading and paragraph.

A normal stylesheet declaration cannot defeat those inline `font-family` declarations. A CSS correction would require higher importance, in practice `!important`.

### Supported seam investigation

Bounded exact-source inspection covered GravityView's oEmbed lifecycle plus WordPress' embed/Classic Editor lifecycle. GravityView exposes oEmbed-related logic including its registration/render methods and hooks such as `pre_oembed_result`; WordPress exposes generic embed-response/lifecycle filters and the generic TinyMCE wpview path.

No inspected seam provides all of the following at once:

- a stable GravityView-specific presentation scope around this placeholder;
- a typography-only correction without replacing or rewriting the response;
- isolation from unrelated WordPress/plugin embed placeholders;
- a stable relationship back to the source embed element for the existing exclusion authority.

Response rewriting, vendor edits, renderer replacement, output buffering, and a second exclusion model remain outside Vazir ownership.

### Exclusion result

The authentic placeholder lives inside a TinyMCE/wpview rendering context that has no usable relationship to the configured source-side `exclude_selectors` boundary. The existing single exclusion authority cannot truthfully determine that this detached embed preview belongs to an excluded source element.

A broad generic `wpview`/`.loading-placeholder ... !important` repair would therefore both affect unrelated embeds and silently bypass the exclusion contract.

The final oEmbed disposition is:

`NO_ADMISSION`

The failure is real, but there is no safe supported production repair within Vazir's current typography ownership and exclusion guarantees.

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
| GravityView oEmbed placeholder | `NO_ADMISSION` | Authentic failure exists, but only generic WordPress/TinyMCE scope is available, inline font requires `!important`, and exclusion association is unavailable. |
| GravityView icon family | `PRESERVED` | Representative GravityView glyph retains the `gravityview` family. |
| WordPress Dashicons | `PRESERVED` | Dashicons retain `dashicons`. |
| Normal-descendant exclusions | `RUNTIME_PROVEN` | React Select and Datepicker repairs remain bounded by the single existing exclusion authority. |
| Detached portal exclusion association | `NOT_PROVEN` | Stable source-control-to-portal association was not established. |
| oEmbed exclusion association | `NO_USABLE_ASSOCIATION` | Authentic wpview/TinyMCE insertion has no stable relationship to the source exclusion boundary; this contributes to `NO_ADMISSION`. |

## Evidence contract

The GravityView Product Evidence profile now has distinct responsibilities:

1. baseline characterization of frontend, authentic editor assets, React Select, Datepicker, icons, and authenticated oEmbed failure;
2. PR #24 production-repair regression verification;
3. bounded detached-portal closure characterization;
4. authentic Classic Editor/TinyMCE oEmbed insertion characterization plus exact lifecycle/source probes.

The profile remains successful when it truthfully records an admitted product disposition such as portal `NOT_PROVEN` or oEmbed `NO_ADMISSION`; a green evidence harness does not mean every host-owned surface is converted to Vazirmatn.

## Closure state

GravityView exact `3.3.4` is **not yet eligible for destination closure** under a destination that requires every remaining boundary to have a final supported disposition other than unresolved `NOT_PROVEN`.

The oEmbed ambiguity is closed: the exact runtime failure has a defensible `NO_ADMISSION` result and should not receive a production repair under the current ownership/exclusion contract.

The detached React Select portal remains the only unresolved exact-3.3.4 typography boundary in this characterization. It must not receive speculative production repair. A later qualification may revisit it only if a stable visible menu can be reached through trustworthy user interaction and can be associated both with GravityView and with the source control/exclusion boundary through a supported mechanism.

No tag, GitHub Release, publication, deployment, or production behavior change belongs to this final-boundary qualification batch.
