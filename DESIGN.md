# Transport Diary design system

## Direction

Use the supplied NShine ERP screenshots and walkthroughs as the product UI reference: a light, white-and-slate interface, confident blue actions, clear business sections, dense but readable operational cards, and consistent navigation. Keep Transport Diary’s own route-and-diary identity and product copy; do not reuse NShine’s name, logo, customer claims, prices, or media.

Transport Diary is the client-facing brand. Kodewaves appears as developer attribution where appropriate. The public site explains the service and plans; private company work stays in tenant workspaces, and platform-owner controls stay in Platform Admin. Preserve the existing role-specific navigation and business workflows while making each panel use the same visual system.

## Palette

| Token | Value | Use |
| --- | --- | --- |
| Primary blue | `#2563EB` | Primary actions, selected controls, active tabs and focus |
| Deep blue | `#1E40AF` | High-contrast headings, secondary actions and hover states |
| Blue tint | `#EFF6FF` | Selection context, icon surfaces and supporting panels |
| Pale blue | `#DBEAFE` | Subtle feature and hero gradients |
| Page background | `#F8FAFC` | Public and console workspace background |
| Surface | `#FFFFFF` | Forms, records, cards and overlay content |
| Rule | `#E2E8F0` | Tables, form boundaries and separators |
| Body ink | `#0F172A` | Primary text |
| Secondary ink | `#64748B` | Supporting text on light surfaces |

Dark mode uses navy/slate surfaces and readable light text. Operational success, warning, and danger states keep their semantic colors and must also use a label, icon, or shape. Do not apply a forest-green theme to primary navigation or controls.

## Type and layout

- Use bundled Inter with system sans-serif fallbacks.
- Keep navigation stable, company scope visible, and primary actions near the work they affect.
- Use clear page headings, grouped business sections, compact KPI cards, readable data tables, and task-focused side panels.
- Keep route graphics for real location or handoff information; mark public dashboard figures as illustrative.
- At narrow widths, reflow filters and actions, preserve the order identifier/status/next action, and allow tables to scroll when they cannot become a readable row layout.
- Keep headers, dropdowns, drawers, dialogs, and navigation customization layered so popups remain clickable and visible.

## Components and behavior

- The shared console, Platform Admin, vendor operations, sign-in, and public pages use the same blue/white/slate palette while retaining their role-specific task structures.
- Tables keep sortable headers and clear column labels. Empty, delayed, failed, loading, and disabled states remain visible and named.
- Destructive actions retain confirmation. Success, warning, and error colors stay semantic rather than becoming brand colors.
- Startup and in-app loading use the Transport Diary route mark and respect reduced-motion preferences.
- Keep open-source copyright, license text, and a current corresponding-source offer accessible to service users. Keep license/source content out of marketing navigation and exclude private operational notes from public source archives.

## Product constraints

- Keep existing Admin, vendor, and driver workflows and feature gates.
- Do not add customer sign-in, a customer portal, Storefront, or commerce.
- New financial records use INR; historical values preserve their stored currency and amount.
- Route illustrations and dashboard figures must be backed by real data or marked as illustrative samples.
