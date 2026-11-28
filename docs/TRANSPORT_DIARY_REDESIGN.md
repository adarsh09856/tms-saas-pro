# Transport Diary redesign

The Codex release owner carries the redesign through implementation, review, fixes and deployment. Batch public pages and signed-in panels into one release review and give one consolidated report. Recheck individual failures when necessary, then rerun the affected release checks together.

## Current delivery sequence

1. Build one searchable workspace sidebar with expandable groups for transport operations, finance, warehouse, team, AI and platform administration when each module and role permits access. Include the existing screens inside those groups and retain the mobile drawer.
2. Apply the Claymorphism visual language across the public site, authentication, dashboard, module lists, forms, detail panels, settings and administration. Keep dense operational tables readable and preserve existing actions and data.
3. Trace booking, dispatch, fleet, delivery proof, invoicing, payments, warehouse stock and available AI workflows through their existing routes. Repair broken screens and permission handling found during that review.
4. Review public and signed-in experiences together at desktop and phone sizes, including role access, navigation, overlays, forms and core workflows. Build the reviewed commit on the VPS, push it to GitHub and deploy it with a rollback path.

The driver app and custom vendor domain work remain deferred while the web panels are completed.

Complete the redesign before releasing workflow additions. Use the supplied NShine reference for product hierarchy and workflow presentation and the [Claymorphism design skill](https://github.com/bergside/awesome-design-skills/tree/main/skills/claymorphism) for visual styling across the public site and every console panel. Keep the Transport Diary mark, slate text, blue primary actions, and clear operational status colors. Use opaque pastel surfaces, rounded panels and soft inset and outer shadows. Keep tables, maps and nested content flat where staff scan dense information. Use real screenshots from fictional staging records.

Use the [marketing demo](https://claymorphism-typeui-0258c8ea.vercel.app/marketing) for the shape and spacing of calls to action and feature cards. Use the [application demo](https://claymorphism-typeui-0258c8ea.vercel.app/application) for rounded controls, restrained color accents, and clear content grouping.

| Surface | Design work | Screenshot and acceptance |
| --- | --- | --- |
| Public site | Rework Home, Services, plans, About and Contact around the offered service. Use a real workspace preview and clear signup and walkthrough links. | Desktop and phone overview, readable navigation, working links and contact flow. |
| Authentication | Share layout, typography and form guidance across sign-in, signup and recovery. | All forms render, respond to validation and enter the expected workspace. |
| Console shell | Put main navigation in one sidebar with a phone drawer. Keep account controls and notifications in the header. | Sidebar links, active state, customization, profile and Messages stay visible and usable with keyboard, mouse and touch. |
| Platform Admin | Organize companies, users, plans, usage, integrations and support around practical administration tasks. | Overview, company list and detail, forms and feedback. Verify access with an authorized admin and a company user. |
| Company operations | Align dashboard, booking, dispatch, fleet, drivers, maintenance and delivery proof with shared page and table patterns. | Capture a booking through dispatch and delivery with fictional records. Check maps, forms, filters and empty states. |
| Finance and warehouse | Align invoices, payments, reports, inventory, receiving and picking with the same layout. Use INR for new transactions. | Capture finance and stock screens and verify monetary totals, permissions and inventory changes. |
| AI, automation and settings | Surface existing features where staff use them. Show review, approvals, failures and audit history where supported. | Capture enabled views and check their real behavior with the applicable roles and provider settings. |

Capture desktop and phone widths in one browser pass on the isolated VPS candidate. Save before and after screenshots per surface, using fictional data throughout. Check overflow, keyboard navigation, focus, loading, empty and error states as part of the same pass. The driver app and custom company domains are deferred.

## Role coverage for the redesign

| Role | Controls to organize and verify |
| --- | --- |
| Platform Admin | Company management, plans, provisioning, support, monitoring and platform settings. Verify that company roles cannot open these routes or call their APIs. |
| Company owner and administrator | Company settings, team membership, permissions and enabled modules. Verify that administration applies only to the current company. |
| Dispatcher and operations staff | Bookings, assignments, dispatch, tracking and delivery proof. Show allowed actions beside the records they act on. |
| Fleet staff | Vehicles, drivers, maintenance, documents and fuel, subject to assigned permissions. |
| Finance staff | INR invoices, receipts, payments and reports, with separate permissions for review and changes where supported. |
| Warehouse staff | Inventory, receiving, transfers, picking and loading, subject to the company's enabled modules and assigned permissions. |
| Staff with AI or automation access | Review suggestions, approve permitted actions, and inspect run history or failures. Configuration and approvals follow the existing role permissions. |

Use the existing ability checks and server authorization as the source of truth. Apply role visibility consistently to sidebar items, page actions, dialogs and direct links. Include allowed and denied paths and two separate fictional companies in the same release review.

After review, verify the built source matches the intended Git changes, push the release, preserve the existing source archive and rollback files, and verify the deployed screenshots and core flows at https://tms.kodewaves.in.

## Candidate evidence

Commit `82df3d7` is staged on the VPS. The combined browser run opened Home, Services, About and Contact without horizontal overflow at 1440px and 390px. A fictional account signed in, opened the dashboard and Create booking, and expanded Companies and plans to reveal Billing Plans at both widths. The homepage preview uses a screenshot from that isolated account.

The remaining review covers signup and recovery, employee and platform role boundaries, drawer and popup interaction, dispatch through delivery proof, finance, inventory, and enabled AI and automation. The candidate has not been published.
