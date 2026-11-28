# Transport Diary redesign

The Codex release owner carries the redesign through implementation, review, fixes and deployment. Batch public pages and signed-in panels into one release review and give one consolidated report. Recheck individual failures when necessary, then rerun the affected release checks together.

Complete the redesign before releasing workflow additions. Use the supplied NShine reference for product hierarchy and workflow presentation. Keep the Transport Diary mark and build the interface around white surfaces, slate text, thin neutral borders and a blue accent for primary actions and selected navigation. Reserve green, amber and red for operational statuses. Use opaque menus and panels, simple dividers and real screenshots from fictional staging records. Avoid translucent surfaces, decorative gradients and filler badges.

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

After review, verify the built source matches the intended Git changes, push the release, preserve the existing source archive and rollback files, and verify the deployed screenshots and core flows at https://tms.kodewaves.in.
