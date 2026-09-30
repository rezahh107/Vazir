# GravityView 3.3.4 typography characterization

## Qualified boundary

This characterization is bound to the exact Owner-supplied runtime exercised by the Product Evidence Lab:

- GravityView `3.3.4` (`gravityview.zip`, `7,569,755` bytes, SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`);
- Gravity Forms prerequisite `3.1.1.1` (SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`).

The licensed package bytes remain outside the repository and outside uploaded evidence. Source evidence records only relative paths, hashes, line numbers, semantic token presence, registered handles, and runtime identity.

Compatibility claims are exact-runtime claims. Other GravityView versions remain `NOT_PROVEN` until separately exercised.

## Previous profile boundary

The earlier GravityView profile proved a real frontend View, search interaction, filtered output, an exclusion fixture, the View editor route, and representative icon ownership when rendered. It did not prove the modern Vantage theme, Gutenberg controls, React Select portals, Datepicker, or oEmbed placeholder typography.

The strengthened fixture explicitly opts the real View into Vantage and creates a normal WordPress Page containing the real registered `gk-gravityview-blocks/view` block. It therefore measures the requested surfaces through the installed GravityView runtime instead of reproducing GravityView components with imitation markup.

## Runtime disposition summary

| Surface | Exact 3.3.4 disposition | Runtime result |
| --- | --- | --- |
| Modern Vantage frontend inner typography | `ALREADY_VAZIRMATN` | Real root, table header, entry value, search label/input/button, pagination, and filtered state resolve to Vazirmatn. No frontend repair is admitted. |
| React Select selected/value/control | `FAIL` | Selected/value text and control resolve to GravityView's explicit editor system stack. The actual search input itself resolves to Vazirmatn. |
| React Select detached menu portal | `NOT_PROVEN` | The real control produces a `gk-select` portal candidate under the top-level document body, but the exact run did not expose a stable visible ARIA listbox whose option typography could be measured. |
| View-block Datepicker | `FAIL` | The authentic `.react-datepicker`, current month, and day text resolve to `"Helvetica Neue", helvetica, arial, sans-serif`; its input remains Vazirmatn. |
| GravityView oEmbed admin placeholder | `FAIL` | The authentic WordPress `parse-embed` route returns GravityView's placeholder; its heading and paragraph retain inline system-font declarations. |
| GravityView icon family | `PASS` | Representative GravityView pseudo-element retains the `gravityview` icon family. |
| WordPress Dashicons | `PASS` | The Gutenberg admin-menu icon retains `dashicons`. |
| `gform-icons-admin` on this exact editor path | `NOT_PROVEN` | No representative node rendered on the exercised GravityView View-block path. |

A source declaration is not classified as a runtime failure unless the corresponding real surface is reached and measured. The detached React Select menu remains `NOT_PROVEN` even though exact source shows `menuPortalTarget={doc.body}` and applies the system-font object to `menuPortal`.

## Modern frontend and `--gv-font-family`

Exact source defines the modern theme token default as `--gv-font-family: inherit` and maps GravityView's `font_family` token to that custom property. Supported GravityView theme override filters also exist.

The real Vantage fixture nevertheless needs no override. Computed typography on every exercised inner text surface resolves to:

`Vazirmatn, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif`

The measured custom-property value on the exercised `.gv-themed.gv-theme-vantage` root is the empty string rather than the literal text `inherit`; that measurement must not be rewritten into a stronger runtime-token claim. The important runtime result is that the real inner typography inherits Vazirmatn correctly.

The configured `.vf-view-excluded` fixture remains `monospace`, proving that the existing shared exclusion authority is still effective for this normal descendant surface.

## Gutenberg fixture and host asset ownership

The exercised block is the actual registered:

`gk-gravityview-blocks/view`

The real editor runs in the top-level wp-admin post editor and exposes the WordPress editor canvas iframe. The exact GravityView View-block assets observed at runtime include its real `view.js`, `view.css`, and `style-view.css` builds.

The installed runtime reports these GravityView-owned handles:

- editor script: `gk-gravityview-blocks-view-editor-script`;
- editor style: `gk-gravityview-blocks-view-editor-style`;
- global block style: `gk-gravityview-blocks-view-style`.

GravityView registers these assets; Vazir does not replace or fork them.

## React Select control

The authentic `.gk-gravityview-blocks .view-selector` renders the selected View text with:

`-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif`

The control resolves to the same explicit system stack. The real combobox input itself resolves to the normal Vazirmatn stack.

Exact installed source explains the result: `DocumentAwareSelect` defines an editor system-font object and applies it through react-select styles. Emotion ownership uses the stable cache key `gk-select`, while generated classes such as `gk-select-...-control` are runtime-generated and are not production selector authority.

A narrow control-side repair candidate exists: stable GravityView semantic scope such as `.gk-gravityview-blocks .view-selector`, attached through the GravityView-owned registered editor-style lifecycle or a narrowly scoped `enqueue_block_editor_assets` integration. Any future repair must still preserve the existing `exclude_selectors` negative-applicability contract and icon ownership.

## Detached React Select menu

Exact installed source portals the menu to the relevant document body. This means the menu is not a normal descendant of the source `.view-selector`, and existing ancestry-based exclusion semantics cannot automatically associate a detached portal with the source control.

Runtime confirmed a real `gk-select` portal candidate under the top-level editor document body, but the exact qualified interaction did not produce a stable visible ARIA listbox whose option text could be measured. The portal/menu typography therefore remains `NOT_PROVEN`, not `FAIL`.

No production repair is admitted for the detached menu in this PR. In particular, do not:

- depend on dynamic Emotion hash classes;
- apply a global rule to every React Select in wp-admin;
- pretend the portal is a descendant of `.view-selector` for exclusion purposes;
- add JavaScript DOM typography mutation merely to bridge the portal boundary.

The next repair batch must first identify a stable GravityView-owned portal association that can preserve exclusion semantics.

## Datepicker

The authentic GravityView View-block `Entries Settings` path exposes the real React Datepicker. Runtime measurements are:

- Datepicker root: `"Helvetica Neue", helvetica, arial, sans-serif`;
- current month text: same stack;
- day text: same stack;
- associated input: normal Vazirmatn stack.

The observed Datepicker is in the top-level editor document and remains inside the GravityView inspector; its popper is a normal `.react-datepicker-popper` descendant rather than a detached source-control portal.

This makes the smallest repair seam comparatively clear: a narrowly scoped `.gk-gravityview-blocks .react-datepicker` rule through the GravityView View-block editor-style lifecycle (or a bounded `enqueue_block_editor_assets` integration). The existing ancestry-based exclusion model can remain applicable on this observed path.

No Datepicker production CSS is included here because the same Owner batch also contains materially uncertain React Select portal and oEmbed boundaries. Qualification remains separate from production admission.

## oEmbed admin placeholder

The profile exercises GravityView oEmbed through WordPress' authenticated `admin-ajax.php` `parse-embed` route with a real GravityView entry URL. It does not call GravityView's private rendering method directly.

The authentic returned placeholder is mounted unchanged into the current authenticated wp-admin document solely for computed-style measurement. The surrounding admin document and placeholder container inherit Vazirmatn, but the placeholder heading and paragraph resolve to GravityView's inline system stack:

`-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif`

A normal stylesheet rule cannot beat those inline declarations; a CSS correction would require `!important`. Exact 3.3.4 returns only the generic `.loading-placeholder` fragment and no stable GravityView-specific wrapper. A broad `.loading-placeholder` override is therefore not admitted until a stable GravityView-owned insertion-context selector is proven.

The plugin source itself must not be edited or rewritten to change the oEmbed HTML.

## Icon and exclusion ownership

Typography qualification is invalid if it fixes text by breaking glyph ownership. The exact runtime preserves:

- GravityView icon font on the representative GravityView icon pseudo-element;
- WordPress `dashicons` on the exercised Gutenberg admin icon.

`gform-icons-admin` remains `NOT_PROVEN` on this path because no representative node rendered.

`vazir_font_options['exclude_selectors']` remains the only exclusion authority. Normal descendant GravityView repairs can reuse the shared `VazirFont_Selector_Boundary`. A detached React portal must not be treated as a descendant of the source control merely to reuse that mechanism.

## Rejected brittle approaches

This qualification does not admit any of the following:

- setting GravityView's frontend theme token merely because an override filter exists;
- adding a broad generic GravityView adapter before runtime admission evidence;
- targeting generated Emotion hash classes;
- global wp-admin React Select overrides;
- JavaScript typography mutation;
- fake `.react-datepicker` or fake GravityView markup;
- broad `.loading-placeholder` rules;
- GravityView or Gravity Forms vendor edits.

## Production disposition

No production typography repair is included in this qualification PR.

The modern frontend already behaves correctly and must remain untouched. The Datepicker has a narrow credible repair seam, but the detached React Select portal still lacks a proven bounded association and the oEmbed placeholder lacks a stable GravityView-specific CSS scope. Shipping only the easy Datepicker correction in this evidence batch would blur qualification and production admission while leaving the materially different uncertain families unresolved.

GravityView is therefore **not CLOSED** by this qualification. The next production batch should be evidence-led and separated by seam:

1. admit the normal-descendant Gutenberg repairs that are independently runtime-proven and exclusion-safe, including the React Select control/value and Datepicker, only after the final production selector set is rechecked against exact 3.3.4;
2. separately resolve or explicitly no-admit the detached React Select portal based on a stable GravityView-owned association;
3. separately resolve or explicitly no-admit oEmbed only if a stable GravityView-owned insertion context can be demonstrated without globally styling generic WordPress placeholders.

No tag, release, public ZIP publication, or deployment is part of this characterization.