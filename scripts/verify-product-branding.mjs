#!/usr/bin/env node

import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const failures = [];

async function translationFiles(directory) {
    const entries = await readdir(path.join(root, directory), { withFileTypes: true });

    return entries.filter((entry) => entry.isFile() && entry.name.endsWith('.yaml')).map((entry) => path.join(directory, entry.name));
}

const translationDirectories = [
    'console/translations',
    'packages/customer-portal/translations',
    'packages/dev-engine/translations',
    'packages/fleetops/translations',
    'packages/iam-engine/translations',
    'packages/registry-bridge/translations',
    'packages/storefront/translations',
];

const visibleSourceFiles = [
    'console/app/controllers/console/admin.js',
    'console/app/controllers/invite/for-user.js',
    'console/app/components/onboarding/verify-email.js',
    'packages/fleetops/addon/components/admin/tracking-page-settings.hbs',
    'packages/ember-ui/addon/components/fleetbase-attribution.hbs',
    'packages/ember-ui/addon/components/fleetbase-attribution.js',
    'packages/core-api/src/Auth/OAuthServer/OAuthScopes.php',
    'packages/core-api/src/Auth/OAuthServer/OAuthServer.php',
    'packages/core-api/src/Notifications/PasswordReset.php',
    'packages/core-api/src/Notifications/UserAcceptedCompanyInvite.php',
    'packages/core-api/src/Notifications/UserInvited.php',
];

for (const directory of translationDirectories) {
    for (const file of await translationFiles(directory)) {
        const source = await readFile(path.join(root, file), 'utf8');
        if (source.includes('Fleetbase')) {
            failures.push(`${file}: contains an upstream product name in customer-visible translations`);
        }
    }
}

const forbiddenVisiblePhrases = [
    /Continue with Fleetbase/i,
    /welcome to Fleetbase/i,
    /Powered by Fleetbase/i,
    /your Fleetbase account/i,
    /registered with Fleetbase/i,
];

for (const file of visibleSourceFiles) {
    const source = await readFile(path.join(root, file), 'utf8');
    for (const phrase of forbiddenVisiblePhrases) {
        if (phrase.test(source)) {
            failures.push(`${file}: contains customer-visible phrase ${phrase}`);
        }
    }
}

const appConfig = await readFile(path.join(root, 'api/config/app.php'), 'utf8');
if (!appConfig.includes("env('APP_NAME', 'Kodewaves Transport ERP')")) {
    failures.push('api/config/app.php: Kodewaves must remain the default application name');
}

// Branding must never erase the upstream legal record. The legal dialog is intentionally
// excluded from the customer-copy scan and checked for provenance instead.
const legalNotice = await readFile(path.join(root, 'packages/ember-ui/addon/components/modals/fleetbase-legal-notice.hbs'), 'utf8');
for (const required of ['Fleetbase Pte. Ltd.', 'GNU Affero General Public License', 'github.com/fleetbase/fleetbase']) {
    if (!legalNotice.includes(required)) {
        failures.push(`legal notice: missing required upstream provenance: ${required}`);
    }
}

if (failures.length) {
    console.error(`Product branding verification failed (${failures.length} issue${failures.length === 1 ? '' : 's'}):`);
    for (const failure of failures) console.error(`- ${failure}`);
    process.exit(1);
}

console.log('Product branding verification passed: Kodewaves customer copy is active and upstream legal provenance is preserved.');
