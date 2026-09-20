# Production Release System

Vazir has one canonical production artifact path. The release object is the installable ZIP, not the repository checkout.

## Canonical production ZIP

Build from a source tree whose plugin header `Version:` and `VAZIR_FONT_VERSION` already contain the intended production SemVer:

```bash
version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' vazir-font-wp.php | head -n1)"
bash scripts/release/build-release.sh . "$version" build/release
```

The builder creates:

- `build/release/vazir-font-wp-<version>.zip`
- `build/release/vazir-font-wp-<version>.zip.sha256`

The ZIP has exactly one consumer root: `vazir-font-wp/`.

The package contract is allowlist-based. Runtime PHP under `includes/`, required CSS/JS, translation resources, README, the five pinned Vazirmatn WOFF2 files, `OFL.txt`, `AUTHORS.txt`, and `Vazirmatn-PROVENANCE.md` are eligible. Repository/CI/release tooling, tests, development dependencies, build output, nested ZIPs, and licensed Gravity Forms/Flow/View packages are not eligible.

The builder normalizes shipped file timestamps and ordering so two builds of the same source/version produce the same ZIP bytes and SHA-256.

## Local validation

```bash
version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' vazir-font-wp.php | head -n1)"
zip="build/release/vazir-font-wp-$version.zip"
sha="$(sha256sum "$zip" | awk '{print $1}')"

bash tests/release/release-contract-tests.sh
bash scripts/release/validate-release.sh . "$zip" "$version" "$sha"
```

`validate-release.sh` verifies archive integrity, canonical root, exact package file set, entrypoint, version mirrors, required assets, pinned Vazirmatn byte identities/provenance, forbidden material, and the supplied checksum.

`tests/release/release-contract-tests.sh` falsifies material failures including version mismatch, missing fonts/license/provenance, forbidden development content, a nested licensed Gravity package, wrong root/entrypoint, and checksum mismatch.

## GitHub Actions dry-run

Run **Vazir Production Release** with `mode=dry-run`, or change release-system/runtime files in a pull request. Dry-run cannot create a production tag or GitHub Release.

The workflow:

1. binds itself to the exact evaluated source SHA;
2. runs the release contract/falsification tests;
3. builds the canonical ZIP once and records its SHA-256;
4. validates that exact ZIP;
5. installs that exact ZIP into a fresh WordPress runtime with `wp plugin install` and activates it;
6. proves runtime/settings hooks and packaged assets from the installed artifact;
7. reuses the real admin settings browser characterization against that installed ZIP;
8. passes the same already-built ZIP and SHA to the Product-Wide Evidence Lab for Gravity Forms, Gravity Flow, GravityView, and combined-stack qualification;
9. emits a bounded JSON manifest;
10. uploads the candidate ZIP, checksum, and manifest as temporary Actions evidence.

Dry-run publication state is always `NOT_ATTEMPTED_DRY_RUN`.

The licensed Product Evidence profiles retain their existing truth boundaries. If a required licensed environment/package cannot execute, that is not promoted to PASS. The release manifest records product qualification as not proven/environment unavailable while the profile jobs remain the detailed evidence source.

## Version handling

There is currently no published GitHub Release history from which automation can truthfully infer a first public version. The release system therefore does **not** choose or bump the first public version.

For publication, the Owner supplies both:

- the exact intended SemVer (`version`); and
- the exact approved `main` commit SHA (`approved_sha`).

The source at that SHA must already have matching `Version:` and `VAZIR_FONT_VERSION` values. If it does not, publication fails closed. Version preparation remains a normal reviewed source change instead of an invisible mutation performed during publication.

A future patch/minor/major resolver can be added after a real release history exists if it materially improves the workflow; it is intentionally not required for the first-public-release boundary.

## Explicit Owner publication

Production publication happens only through a manual `workflow_dispatch` of **Vazir Production Release** with:

- `mode=publish`;
- an explicit `version`; and
- an explicit `approved_sha`.

Publication never occurs on push, pull request, merge, or ordinary green CI.

Before publication, automation verifies that:

- dispatch is on `main`;
- `approved_sha` equals the dispatch SHA;
- remote `main` still equals `approved_sha` after qualification;
- the requested version matches both internal version mirrors;
- the production tag/release identity is not already occupied;
- release contract, exact-ZIP validation, clean install/runtime smoke, installed admin settings browser characterization, and the artifact Product Evidence Lab have succeeded for the exact candidate bytes.

Only then may the workflow create `v<version>` and its GitHub Release assets.

## Publication states and last-mile verification

The bounded manifest distinguishes:

- `QUALIFIED_NOT_PUBLISHED`: candidate qualification succeeded but no irreversible publication has happened yet;
- `PUBLISHED`: the GitHub Release was created, but consumer-facing bytes have not yet completed last-mile verification;
- `PUBLISHED_AND_VERIFIED`: the workflow downloaded the published ZIP asset again, matched its SHA-256 to the qualified artifact, revalidated its archive/package contract, and clean-installed/smoked the downloaded bytes successfully.

A successful `gh release create` alone is therefore never enough for `PUBLISHED_AND_VERIFIED`.

If last-mile verification fails after publication, the workflow stops with the release in the truthful `PUBLISHED` state; the failure must be investigated rather than relabeled as verified.

## What automation proves

For a successful exact-Head run, release automation proves the identity and package contract of the exact ZIP, deterministic checksum, clean WordPress installation/activation, representative runtime initialization, bundled font availability, the packaged real settings surface, and whichever Product Evidence profiles actually executed successfully against that same ZIP.

It does **not** prove every WordPress/theme/plugin combination, every Gravity Forms/Flow/View configuration, every browser/device, or behavior that the existing evidence architecture classifies as unobservable/unsupported. Source CI remains useful regression evidence but is not substituted for artifact qualification.

## Human review that remains useful

Before authorizing publication, review the release changes and user-facing release notes/intent, confirm the chosen public version is the version you intend to expose, and check that the exact candidate run is the one you are approving. Do not manually assemble ZIP contents, calculate checksums, create manifest metadata, or infer which artifact was qualified; those are owned by the automated path.
