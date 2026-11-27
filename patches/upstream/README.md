# Upstream package patches

This TMS repository keeps the upstream Fleetbase packages as pinned submodules. These patches carry the local TMS changes without replacing those upstream repositories or package identities.

The patch set is tied to the package commit pins recorded in the root repository. Apply it after initializing submodules and before building:

```sh
git submodule update --init --recursive
node scripts/apply-upstream-package-patches.mjs packages patches/upstream
```

The application is idempotent: it first detects an already-applied patch, applies a patch to its pinned source, and stops if the source does not match either state. A test file absent from an upstream pin is skipped; missing runtime source files remain an error. Docker API and console builds apply the same patches automatically.

The patches cover `core-api`, `ember-core`, `ember-ui`, `fleetops`, `registry-bridge`, `ledger`, and `pallet`. The upstream package `LICENSE.md` files and the Fleetbase open-source notice remain part of the package source and stay accessible in the product.
