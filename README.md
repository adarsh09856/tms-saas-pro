# Kodewaves Transport ERP

Kodewaves Transport ERP is a branded, multi-company transport operations platform built from the Fleetbase modular logistics codebase. It brings dispatch, fleet management, driver operations, finance, and optional warehouse workflows into one system.

## Product surfaces

- **Platform administration:** organization onboarding, access, configuration, and operational oversight.
- **Company console:** bookings and dispatch, fleet groups, driver and vehicle records, route planning, tracking, maintenance, finance, reports, and integrations.
- **Driver operations:** assigned jobs, manifests, navigation handoff, delivery activity, proof capture, inspections, and offline-capable workflows where supported by the mobile client.

## Fleet groups and driver–vehicle pairing

A fleet is a company-scoped operating group for drivers and vehicles. The fleet roster lets authorized staff add members and pair a driver with one of the vehicles in that same fleet. The API checks both memberships before applying a roster pairing. A driver's default vehicle remains exclusive: assigning a vehicle to another driver moves that default assignment, while the fleet memberships remain independent.

## Modules in this source tree

Fleet-Ops supplies staff-managed transport orders, dispatch, routes, manifests, fleet resources, live tracking, telematics, inspections, and maintenance. Ledger supplies financial records and invoicing. Pallet supplies warehouse and stock operations. Customer contacts remain company-scoped records, and staff create bookings for them. The shipped TMS does not include a customer portal, customer login, Storefront, or commerce workflow. Authorized public shipment tracking remains read-only.

## SaaS billing and FASTag operations

The platform includes versioned organization plans, Stripe subscription checkout and customer portal flows, signed and idempotent billing webhooks, subscription lifecycle state, configurable grace periods, module/seat/driver/storage/API entitlements, and usage reporting. Billing enforcement defaults to off so existing organizations are not locked out during rollout. Configure Stripe keys and the webhook secret, publish plans with verified recurring Stripe Price IDs, migrate the database, and enable `BILLING_ENFORCEMENT_ENABLED` only after reviewing tenant entitlements.

FASTag supports tenant-scoped vehicle tag history, masked tag references, CSV statement imports, checksum and provider transaction deduplication, unmatched record quarantine and rematching, optional statement-control-total reconciliation, reversal/refund records, audited dispute and resolution records, suggested trip matching, approved double-entry ledger posting, and toll cost reports. CSV headers are `provider_transaction_id,tag_reference,passage_at,amount,currency`; optional columns include `provider_account_id,received_at,status,reversal_of,plaza_name,vehicle_registration`. The first release uses validated provider statement imports. Live issuer synchronization requires an approved provider integration and credentials; no direct FASTag issuer connection is included.

The code changes are prepared for deployment validation, not a verified production release. Payment, mapping, SMS, email, GPS, storage, and FASTag provider integrations require their own credentials and configuration.

### First deployment setup

1. Build the API and console from the checked-out source with `docker compose up --build`; the Compose configuration overlays the local package code into both images. Run the existing database migrations in the application container. The SaaS and toll tables are created by migrations in `packages/core-api` and `packages/fleetops`. Build the mobile app separately from `apps/navigator-app`.
2. Configure `APP_URL`, database, mail, storage, maps/routing, and Stripe environment values from `api/.env.example`. Keep `BILLING_ENFORCEMENT_ENABLED=false` during initial setup.
3. In Stripe, create recurring Prices for paid plans and configure a webhook pointing to `/webhooks/stripe/billing` under the API host (include any configured `API_PREFIX`). Subscribe it to subscription lifecycle and invoice paid/failed events, and copy the signing secret into `STRIPE_WEBHOOK_SECRET`.
4. As a system administrator, open **Admin → Billing Plans** and publish plans. Prices are entered in major currency units; paid plans require a Stripe Price ID whose amount, currency, and interval match. Configure `seats`, `drivers`, `storage_bytes`, `api_requests`, and `modules` entitlements.
5. Validate a tenant signup, checkout, webhook updates, portal access, usage reporting, and entitlement limits in staging. Then enable `BILLING_ENFORCEMENT_ENABLED=true` after checking each tenant has a suitable plan.
6. To use FASTag, upload a CSV in **Fleet Ops → FASTag & Tolls**. Enter the provider account ID and optional statement control total, map tags to vehicles with their effective dates, review import exceptions, and allocate or dispute transactions. Verify ledger accounts and currency before posting.

Live FASTag sync, provider balance checks, and issuer-side dispute submission are not implemented without an issuer API agreement and integration. The current tool handles uploaded statements and internal finance review.

## Development

The repository is a multi-package project. The API is Laravel/PHP, the staff console is Ember.js, and real-time events use the SocketCluster service. Feature packages are Git submodules under `packages/`.

Use the project's Docker Compose development setup and keep API, console, and submodule versions aligned. The console requires Node.js 22 or newer according to its package manifest. Configure secrets, mail, storage, maps, routing, and payment providers through environment variables; do not commit production credentials.

## License and upstream notices

This product source contains Fleetbase code and first-party extensions. The applicable upstream source is licensed under AGPL-3.0-or-later, with copyright and license notices retained in `LICENSE.md` and the package manifests. Network-hosted modified versions must comply with the license terms. A separate commercial license is available from the upstream rights holder for proprietary white-label SaaS use. Third-party modules retain their own licenses.
