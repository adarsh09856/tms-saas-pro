# Kodewaves TMS design system

## Direction

**Route Atlas** remains the product's operational design direction: connected routes, stable record identifiers, precise rules, and labels that remain readable under fast scanning. Its confirmed visual palette is forest green and warm ivory across the whole product. It does not imitate another Kodewaves product.

The Admin workspace, transport-company workspace, sign-in, driver experience, and public pages use one system. They retain distinct task structures and role-specific controls. A vendor's own logo and public-site branding remain tenant-configurable; central administration remains Kodewaves branded.

## Palette

| Token | Value | Use |
| --- | --- | --- |
| Forest | `#426B57` | Primary actions, selected controls, links and progress |
| Deep forest | `#294B3A` | Navigation, brand marks and high-contrast surfaces |
| Pale green | `#E8EFE8` | Selection, focus context and supporting panels |
| Warm gold | `#B4873A` | Warnings and time-sensitive attention |
| Warm ivory | `#F6F6EF` | Workspace and public-page background |
| Surface | `#FFFFFF` | Forms, records and overlay content |
| Rule | `#D8DFD3` | Tables, form boundaries and separators |
| Body ink | `#27372D` | Primary text |
| Secondary ink | `#59665A` | Supporting text on light surfaces |

Dark operational surfaces use deep forest and readable ivory text. Operational status must use text or shape in addition to color. Do not leave Fleetbase blue, purple, or generic slate controls mixed into the green theme.

## Type and layout

- Use the bundled Inter family for product text, with system sans-serif fallbacks.
- Use tabular numerals for IDs, amounts, distances, and timestamps.
- Keep navigation stable, company scope visible, and primary actions near the work they affect.
- Use dense, readable records and timelines for operational work. Use route graphics only for real location or handoff information.
- At narrow widths, reflow filters and actions, preserve the order identifier/status/next action, and allow tables to scroll when they cannot become a readable row layout.

## Components and behavior

- The shared console, Admin, vendor, driver, sign-in, and public pages use the same forest and ivory palette while preserving each panel's established layout and behavior. Use deep forest navigation, warm-ivory work areas, readable white record surfaces, and forest selection states.
- Tables keep sortable headers and clear column labels. Empty, delayed, failed, loading, and disabled states remain visible and named.
- Copper is reserved for exceptions and urgent actions; destructive actions retain their existing confirmation behavior.
- Startup and in-app loading use the Kodewaves route mark and respect reduced-motion preferences.
- Keep `Open-source notices`, the AGPL text, copyright holders, and corresponding source links accessible. Do not display upstream commercial-license promotion as product UI.

## Product constraints

- Keep all existing Admin, vendor, and driver workflows and feature gates.
- Do not add customer sign-in, a customer portal, Storefront, or commerce.
- New financial records use INR; historical values preserve their stored currency and amount.
- Route illustrations and dashboard figures must be backed by real data or marked as illustrative samples.
