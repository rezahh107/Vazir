# Vazir Product-Wide Reproducible Evidence Lab

This directory contains the reusable core and licensed integration profiles for exact-Head runtime characterization.

Profiles:

- `wordpress`: delegated to the repository's existing WordPress smoke/browser CI; it is not duplicated here.
- `gravityforms`: the existing deep licensed Gravity Forms profile retained under `tests/gravityforms-evidence-lab/`.
- `gravityflow`: Gravity Forms + Gravity Flow + Vazir, with real workflow/inbox state.
- `gravityview`: Gravity Forms + GravityView + Vazir, with a real View over synthetic entries.
- `gravity-stack`: Gravity Forms + Gravity Flow + GravityView + Vazir in one runtime, reusing representative individual fixtures.

The shared core owns only exact package acquisition/identity, runtime identity capture, and small browser helpers. Profile fixtures and assertions remain profile-specific. A profile PASS proves only the scenarios recorded for that profile.

Licensed packages are downloaded into an ephemeral package directory and must never be committed or uploaded as evidence artifacts. The Vazir source symlink is an explicit seam for a later production-ZIP qualification batch.
