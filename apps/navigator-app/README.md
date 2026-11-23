# Kodewaves Driver

React Native driver application bundled with the Kodewaves Transport ERP clone. It supports driver sign-in, assigned tasks, navigation, proof of delivery, chat, vehicle/fuel reports, issue reporting, and location tracking while a driver is online. The source is derived from Fleetbase Navigator and remains under its upstream AGPL-3.0-or-later license; see `LICENSE` and preserve notices when redistributing.

## Configure for your deployment

1. Copy `.env.example` to `.env`.
2. Set `FLEETBASE_HOST` to the API host for this Kodewaves deployment and provide an appropriate `FLEETBASE_KEY` for local development if needed.
3. Set `GOOGLE_MAPS_API_KEY` and the platform-specific Google Maps credentials.
4. Replace the sample application identifier and link prefix if your organization uses a different reverse-domain ID. Set up corresponding signing credentials for iOS and Android.
5. Install dependencies with Yarn, then run the platform command from `package.json`.

The mobile app uses Fleetbase API and SDK compatibility names internally. The root API and console branding do not change those protocol identifiers. Configure privacy policy, terms, maps, push notifications, OAuth credentials, and production API/socket hosts before distribution.
