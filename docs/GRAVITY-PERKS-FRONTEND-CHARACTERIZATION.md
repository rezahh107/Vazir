# Gravity Perks frontend add-on typography characterization

## Qualified evidence boundary

This document records exact-runtime typography qualification for two Gravity Perks add-ons used on Gravity Forms frontend surfaces. It does **not** add a production adapter or a version whitelist.

Canonical qualification evidence:

- repository: `rezahh107/Vazir`;
- evidence Head: `d2c04d2d565dc822b64777f53ee979abdf9c5b12`;
- Product-Wide Reproducible Evidence Lab run: `36846776686` — all eight profiles passed on that exact Head;
- repository CI run: `36846776640` — all jobs passed on that exact Head;
- Gravity Forms Selector Admission run: `36846776569` — passed on that exact Head;
- WordPress `7.1`;
- PHP `8.3.35` in the Product-Wide Evidence Lab;
- Twenty Twenty-Five;
- Gravity Forms `3.1.1.1`, SHA-256 `542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`;
- Gravity Perks `2.3.16`, SHA-256 `a160d166fb7894b0dfc558ae92e0c230a1336ed2a81e78fa1216be72b1024e7c`;
- GP Advanced Select `1.1.21`, SHA-256 `d83424bfac712e73d772e54e8740b828c52b7c118cfa9aac71646233a6fdcca2`;
- GP File Upload Pro `1.5.13`, SHA-256 `fdab5621dc0c1b9d33384696f554ef9ac0d646a70f8cee652a1bc05c43f8f7ce`.

The same Product-Wide Evidence Lab run also passed the separate `gravity-addons-stack` profile with Gravity Flow `3.1.0`, GravityView `3.3.4`, Gravity Perks, both add-ons, Gravity Forms, and Vazir active together. The retained GravityView browser regression profile also passed on the canonical evidence Head after qualification-harness reachability races were repaired; those harness repairs changed no production PHP, CSS, JavaScript, selectors, or adapter behavior.

Production capability admission remains version-neutral. These exact package versions are evidence identities only. Future versions are `NOT_PROVEN` until separately exercised; they are not blocked merely because their version differs.

## GP Advanced Select 1.1.21

### Disposition

`NATIVE_INHERITANCE` — `NOT_REQUIRED` production repair.

Exact source and browser evidence agree: the Tom Select text surfaces used by this Perk inherit the surrounding font rather than replacing it with a competing family. The authentic browser fixture resolved every exercised material inner text surface to Vazirmatn without a GP Advanced Select-specific production rule.

### Exercised browser surfaces

The dedicated `gp-advanced-select` profile proved Vazirmatn on:

- the Tom Select control;
- the searchable inner input;
- the initial selected value;
- a visible dropdown option;
- the selected value after keyboard selection;
- an option after reopening the dropdown;
- the no-results message;
- a multiselect selected-item chip;
- the rebuilt control after the Gravity Forms page-render lifecycle.

The profile also exercised real focus, keyboard selection, dropdown reopen, no-results rendering, multiselect rendering, and widget rebuild. It observed exactly four unique bundled Vazirmatn WOFF2 requests for the rendered fixture (`300`, `400`, `500`, `700`) with no duplicate URL request.

### Explicit boundary

Dynamically lazy-loaded options produced by GP Populate Anything are `NOT_PROVEN` by this profile. That separate Perk/lazy-loading lifecycle is intentionally outside the deterministic Advanced Select fixture and must not be inferred from ordinary Tom Select option coverage.

## GP File Upload Pro 1.5.13

### Disposition

`NATIVE_INHERITANCE` — `NOT_REQUIRED` production repair.

The exact runtime preserved Vazirmatn through the uploader lifecycle, including the detached crop editor. No File Upload Pro-specific production typography adapter was necessary.

### Exercised browser surfaces and interactions

The dedicated `gp-file-upload-pro` profile proved Vazirmatn on:

- drop-area guidance;
- the select-files action;
- a rendered invalid-extension validation/error message;
- uploaded filename text;
- uploaded file-size text;
- detached crop-editor Cancel and Save/Crop actions;
- filename after a successful crop/save cycle;
- drop-area guidance after a real `gform_post_render` rebuild;
- the detached crop Cancel action after rerender/reopen.

The same exact-Head scenario proved that:

- the crop lightbox is detached from the Gravity Forms field ancestry;
- Cancel closes the editor;
- Save/Crop was enabled, the real action was dispatched, the lightbox closed, and the post-crop file state remained measurable;
- the component survives a Gravity Forms page-render lifecycle without duplicate roots;
- the crop editor can be reopened after rerender.

The profile observed exactly four unique bundled Vazirmatn WOFF2 requests for the rendered fixture (`300`, `400`, `500`, `700`) with no duplicate URL request.

### Harness races falsified during qualification

Earlier failures were evidence-harness defects, not production typography failures:

1. the test initially expected a crop mount target before File Upload Pro's real lifecycle had created the detached editor state;
2. the test then measured/reused transient Vue file/crop nodes before the product-owned upload/cropper lifecycle was stable;
3. a bounded intermediate run correctly refused to claim crop-save completion when no stable closure signal was observed rather than converting an unproved interaction into PASS.

The final profile waits for the authentic upload DONE/progress boundary, re-queries the live filename node, and waits for the rendered cropper image/stencil before exercising Save. On the canonical exact-Head run, the Save action then produced the observable completion signal and post-crop state required for PASS.

Those harness repairs do not alter product behavior and do not constitute a Vazir production repair.

### Explicit boundaries

Exact 1.5.13 did not render a separate textual crop heading/guidance node in the exercised crop UI; that surface is `NOT_REACHABLE` rather than inferred.

Its upload progress indication in this fixture is visual and does not expose a separate textual progress/status surface; textual progress/status typography is therefore `NOT_REACHABLE` by this evidence.

## Combined coexistence

The `gravity-addons-stack` profile passed on the canonical exact Head with the retained Gravity stack and both add-ons enabled together. It proved representative same-page coexistence, material inner text inheritance for both add-ons, real Advanced Select interaction, File Upload Pro upload/crop/rerender behavior, and single-authority bundled Vazirmatn font delivery.

This combined profile is a regression/coexistence instrument, not exhaustive compatibility proof for every Gravity Forms/Flow/View/Perks configuration.

## Production consequence

No production PHP, CSS, JavaScript, option schema, font-delivery authority, or third-party asset was changed for either add-on. The qualification outcome is intentionally evidence-only:

- preserve normal Vazir/Gravity Forms frontend inheritance;
- do not add GP Advanced Select-specific selectors;
- do not add GP File Upload Pro-specific selectors or detached-portal overrides;
- keep exact package versions in the Evidence Lab as proof identities, not production admission gates;
- require new exact-runtime evidence before admitting a future correction if a later package materially defeats inheritance.
