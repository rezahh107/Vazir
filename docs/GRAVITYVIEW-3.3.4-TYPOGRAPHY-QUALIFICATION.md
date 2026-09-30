# GravityView 3.3.4 Typography Qualification

This document defines the exact qualification boundary for the remaining GravityView typography surfaces in the Owner-supplied GravityView `3.3.4` package. It is evidence-first: source declarations identify risks, while production repair requires authentic runtime computed-style evidence.

## Exact authority

- GravityView: `3.3.4`, `gravityview.zip`, 7,569,755 bytes, SHA-256 `af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829`, entrypoint `gravityview/gravityview.php`.
- Gravity Forms prerequisite: `3.1.1.1`, SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`.
- Licensed package bytes and extracted source are never committed or uploaded as evidence.

## Previous profile boundary

Before this qualification batch, the dedicated `gravityview` profile proved only a real frontend View/search interaction, search input/button typography, filtered output, pagination when rendered, an exclusion fixture, the classic GravityView View editor body, and a representative GravityView icon family when rendered. That earlier PASS did not prove the modern themed inner frontend, Gutenberg React Select controls or their detached portal, the Gutenberg Datepicker, or the oEmbed admin placeholder.

## Exact source risks under qualification

The exact installed source is probed structurally without exporting source text. Evidence records only relative file paths, SHA-256 digests, sizes, semantic token presence, line numbers, registered block asset handles, plugin identity, and version.

- Modern GravityView theme typography defines `--gv-font-family` with default `inherit`; this is a compatibility signal, not runtime PASS by itself.
- `src/PageBuilder/Gutenberg/shared/js/document-aware-select.js` applies an explicit editor system stack to the React Select `container` and `menuPortal` styles and portals the menu to the component document body.
- `src/PageBuilder/Gutenberg/build/view.css` contains a direct system `font-family` for `.react-datepicker`.
- `src/Media/oEmbed.php` contains inline system `font-family` declarations on the admin `.loading-placeholder` heading and paragraph.
- GravityView registers supported theme override filters and real Gutenberg block editor script/style handles; those seams are recorded before any repair recommendation.

## Strengthened authentic fixtures

The profile now creates:

1. a real GravityView table View explicitly opted into the modern `vantage` theme;
2. a frontend page rendering that View with the existing exclusion fixture;
3. a WordPress page containing two actual `gk-gravityview-blocks/view` blocks: one configured with the real View and one unconfigured for authentic placeholder state;
4. a real GravityView entry URL and, when public APIs can construct it, a WordPress `core/embed` editor fixture used only to test authentic oEmbed reachability.

No fake React Select, Datepicker, GravityView block, or `.loading-placeholder` markup is manufactured.

## Evidence contract

The browser profile records exact computed `font-family` values and dispositions for representative modern frontend text, React Select value/placeholder/input/control, the real portaled menu and option, the real Datepicker root/header/day/input when reachable, and the oEmbed placeholder only when an authentic editor route actually renders it. Source risk and runtime disposition remain separate.

Expected dispositions are `PASS`, `FAIL`, `ALREADY_VAZIRMATN`, `NOT_PROVEN`, `NOT_REACHABLE`, `NOT_EXECUTED`, or `ENVIRONMENT_UNAVAILABLE`. A reproduced typography defect is product evidence and does not by itself fail the harness. Harness failure is reserved for an inability to execute a deterministic evidence contract truthfully or a regression of behavior that is already required to remain green.

## Repair-boundary rules

- Already-correct modern frontend inheritance receives no adapter or token override.
- GravityView/WordPress/Gravity Forms icon families remain host-owned and must not be forced to Vazirmatn.
- The existing `exclude_selectors` option remains the only exclusion authority.
- A React Select menu portaled to `document.body` is not a normal descendant of `.gk-gravityview-blocks`; future repair must account for that boundary instead of pretending wrapper-descendant CSS covers it.
- Dynamic Emotion hash class names are evidence details, not preferred production authority.
- Datepicker repair, if admitted, should attach narrowly through the real GravityView block-editor stylesheet lifecycle rather than global editor CSS.
- oEmbed repair is not admitted from source alone; authentic runtime reachability is required first.

## Final exact-Head disposition

Pending execution of the strengthened exact-Head Product-Wide Reproducible Evidence Lab. This section must be updated from executed artifacts before the qualification PR is considered complete.
