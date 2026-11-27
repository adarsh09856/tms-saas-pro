import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import assert from 'node:assert/strict';

const [distArg, hostArg, releaseId] = process.argv.slice(2);
assert(distArg && hostArg && releaseId, 'Usage: node scripts/prepare-console-release.mjs DIST HTTPS_HOST RELEASE_ID');
const host = new URL(hostArg);
assert.equal(host.protocol, 'https:');
assert(/^[a-zA-Z0-9.-]+$/.test(host.hostname));
assert(/^[a-zA-Z0-9-]+$/.test(releaseId));
const dist = path.resolve(distArg);
const indexFile = path.join(dist, 'index.html');
let html = fs.readFileSync(indexFile, 'utf8');
const script = html.match(/<script\s+src="([^"]*assets\/@fleetbase\/console-[^"]+\.js)"[^>]*><\/script>/);
assert(script, 'Expected one fingerprinted console application script');
const oldSrc = script[1];
const oldFile = path.resolve(dist, `.${oldSrc}`);
assert(oldFile.startsWith(dist + path.sep));
let js = fs.readFileSync(oldFile, 'utf8');
const marker = 'define("@fleetbase/console/config/environment",';
const start = js.lastIndexOf(marker);
assert(start >= 0, 'Embedded build configuration was not found');
let configTail = js.slice(start);
assert(configTail.includes('disableRuntimeConfig:!0') || configTail.includes('disableRuntimeConfig:!1'));
configTail = configTail.replace('disableRuntimeConfig:!0', 'disableRuntimeConfig:!1');
const socket = configTail.match(/socket:\{[^}]+\}/);
assert(socket && socket[0].includes('secure:!0'), 'Release requires secure WebSocket defaults');
const updatedSocket = socket[0].replace(/port:(?:"38000"|38000|"443"|443)/, 'port:443');
assert(updatedSocket.includes('port:443'));
configTail = configTail.replace(socket[0], updatedSocket);
js = js.slice(0,start) + configTail;
const content = Buffer.from(js);
const fingerprint = crypto.createHash('md5').update(content).digest('hex');
const newSrc = oldSrc.replace(/console-[a-f0-9]+\.js$/, `console-${fingerprint}.js`);
const newFile = path.resolve(dist, `.${newSrc}`);
const integrity = ['sha256','sha512'].map(name=>`${name}-${crypto.createHash(name).update(content).digest('base64')}`).join(' ');
fs.writeFileSync(newFile, content);
html = html.replace(script[0], `<script src="${newSrc}" integrity="${integrity}"></script>`);
html = html.replace('initial-scale=1, maximum-scale=1','initial-scale=1').replace('<title>Kodewaves Transport ERP</title>','<title>Transport Diary</title>').replace('<title>Kodewaves TMS</title>','<title>Transport Diary</title>').replace('content="#0b1220"','content="#1e40af"').replace('color="#16b8a6"','color="#2563eb"');
const themeName = `kodewaves-theme-${releaseId}.css`;
fs.copyFileSync('console/app/styles/kodewaves-theme.css', path.join(dist,themeName));
html = html.replace(/<link[^>]*id="kodewaves-theme-link"[^>]*>/g,'');
html = html.replace('</head>', `<link id="kodewaves-theme-link" rel="stylesheet" href="/${themeName}"></head>`);
fs.writeFileSync(indexFile,html);
const runtimeConfig = { API_HOST:host.origin, API_NAMESPACE:'int/v1', SOCKETCLUSTER_HOST:host.hostname, SOCKETCLUSTER_PATH:'/socketcluster/', SOCKETCLUSTER_SECURE:true, SOCKETCLUSTER_PORT:443 };
fs.writeFileSync(path.join(dist,'fleetbase.config.json'),JSON.stringify(runtimeConfig,null,2));
const extensions = JSON.parse(fs.readFileSync(path.join(dist,'extensions.json'),'utf8'));
const assetManifestPath = path.join(dist, 'asset-manifest.json');
assert(fs.existsSync(assetManifestPath), 'Console asset-manifest.json is required for release validation');
const assetManifest = JSON.parse(fs.readFileSync(assetManifestPath, 'utf8'));
const localAssetPath = (uri) => {
    const pathname = decodeURIComponent(new URL(uri, host.origin).pathname);
    const file = path.resolve(dist, `.${pathname}`);
    assert(file.startsWith(dist + path.sep), `Asset path escapes the release directory: ${uri}`);
    return file;
};

for (const extension of extensions) {
    const bundle = assetManifest.bundles?.[extension.name];
    assert(bundle, `Asset manifest is missing extension bundle: ${extension.name}`);
    for (const asset of bundle.assets ?? []) {
        const file = localAssetPath(asset.uri);
        assert(fs.existsSync(file) && fs.statSync(file).isFile(), `Missing ${extension.name} ${asset.type} asset: ${asset.uri}`);
    }
}

for (const [, uri] of html.matchAll(/(?:src|href)="(\/[^\"]+)"/g)) {
    if (uri.startsWith('//')) continue;
    const file = localAssetPath(uri);
    assert(fs.existsSync(file) && fs.statSync(file).isFile(), `Missing Console entry asset: ${uri}`);
}

const report = { release:releaseId, preparedAt:new Date().toISOString(), script:newSrc, scriptSha256:crypto.createHash('sha256').update(content).digest('hex'), theme:themeName, extensions };
fs.writeFileSync(path.join(dist,'release.json'),JSON.stringify(report,null,2));
console.log(JSON.stringify({release:report.release,script:report.script,theme:report.theme}));
