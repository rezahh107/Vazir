# Gravity Perks evidence profile

This profile is qualification infrastructure for the exact Owner-supplied Gravity Perks 2.3.16 package. It does not claim generic Gravity Perks support and it does not ship a production adapter.

The profile creates an ephemeral real Perk plugin using the `Perk: True` header and the exact runtime `GWPerk` / `GP_Perk` mechanism. The fixture exists only inside the temporary WordPress lab and is never part of the Vazir production artifact.

It independently measures:

- the ordinary Gravity Perks wp-admin management page;
- the standalone Documentation document produced by `GWPerksPage::load_documentation()`;
- the standalone Perk Settings document produced by `GWPerksPage::load_perk_settings()`;
- loaded stylesheet resources and WordPress style-pipeline sentinels;
- Google Fonts request attempts and any `fonts.gstatic.com` font requests;
- Vazirmatn requests inside each document boundary;
- protected Dashicons / GFFontAwesome / FontAwesome families when actually rendered.

A typography mismatch is evidence (`FAIL` disposition for that surface), not a workflow failure. The workflow fails only when required qualification evidence cannot be produced truthfully. Missing optional surfaces remain `NOT_PROVEN`.
