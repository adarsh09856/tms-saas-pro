# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- The Transport Diary Platform Admin manages transport-company tenants, staff access, plans, entitlements, domains, integrations, and platform operations.
- Transport-company owners and staff manage their company contacts, bookings, dispatch, drivers, vehicles, delivery progress, finance, and enabled warehouse operations.
- Drivers receive assigned work and report activity and delivery outcomes from supported driver tools.
- Customers are company-scoped contacts. Company staff create and manage bookings for them; customers do not sign into a portal.

## Product Purpose

Transport Diary gives the platform owner central control of a multi-tenant transport management service while each transport company operates inside its own workspace. It connects booking, dispatch, delivery, fleet, driver, finance, and optional warehouse work.

## Positioning

One centrally administered TMS serves the platform owner and multiple transport companies. Each company has isolated operational records and may use a generated subdomain or verified custom domain. The vendor hostname opens that company's authenticated staff workspace and approved public/tracking pages through the same shared application and database; it does not create a separate codebase or database. Platform Admin remains on the central TMS host.

## Operating Context

Company staff create orders and coordinate their lifecycle through dispatch, driver execution, delivery proof, and financial follow-up. Drivers work from assigned jobs and supported mobile tools. A vendor's own hostname can present its authenticated workspace, company information, and restricted shipment tracking. Platform Admin remains at the central TMS host.

## Capabilities and Constraints

- Preserve the seven quoted module areas: SaaS Administration, Company Setup, Bookings & Dispatch, Fleet & Maintenance, Driver Mobile App, Finance & Reporting, and Warehouse & Stock. Warehouse availability may be controlled by company plan or entitlement.
- Keep company data scoped by tenant membership and company identity across API requests, files, exports, queued work, socket events, and driver sync.
- Do not add a Customer Portal, customer login, customer self-service order creation, Storefront, or commerce workflow.
- Public tracking is read-only and exposes only the shipment details authorized for sharing.
- Use one shared application and database for vendor workspaces, company pages, and central operations. Vendor hostnames resolve to the matching company record; authentication and authorization still enforce the tenant boundary.
- SMTP is for centrally controlled transactional delivery, with optional verified vendor sender domains. Mailboxes, inbound mail, IMAP, and webmail are out of scope.
- Use INR as the only currency for new platform transactions and configuration. Preserve the currency and amounts on existing financial records; do not relabel historical foreign-currency values as INR.
- Keep applicable open-source license, copyright, third-party notice, and corresponding-source access available. Remove Fleetbase product promotion while retaining legally required attribution.
- Keep provider-dependent payments, maps, telematics, messaging, toll, and email behavior gated until configuration and credentials are supplied and verified.

## Brand Commitments

- Product name: Transport Diary.
- Kodewaves remains the software author and copyright attribution where applicable; preserve all third-party copyright, open-source license, and source-access notices.
- The visual system should be distinctive to transport operations and should not duplicate another Kodewaves product one-to-one.
- Preserve accurate product language and the required open-source notices and corresponding-source access.

## Evidence on Hand

- `Transport_Management_Final_Reviewed.pptx`: reviewed transport roles, company-scoped contacts, staff-created bookings, order lifecycle, driver/POD, finance, tenant security, and acceptance criteria.
- `DOC-20261031-WA0004.pptx`: expanded scope, including FASTag/toll import and provider-dependent requirements.
- `Kodewaves_Quotation_Transport_Daires.pdf`: seven priced modules, estimate, milestones, client inputs, acceptance window, support period, exclusions, and third-party costs.
- The repository contains the Laravel API, Ember console, FleetOps, Ledger, Pallet, Navigator, public pages, and deployment configuration used to assess the current implementation.

## Product Principles

- One operational record should carry context across the transport handoffs.
- Platform control and company operations stay distinct, with company data isolated.
- Staff own customer booking and communication workflows; public tracking remains limited and read-only.
- Domain and sender setup resolve into centrally controlled tenant configuration.
- Preserve useful transport capability and data while removing out-of-scope customer commerce and Fleetbase promotion.
