import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const [packagesArg, patchesArg] = process.argv.slice(2);

if (!packagesArg || !patchesArg) {
    console.error('Usage: node scripts/apply-upstream-package-patches.mjs PACKAGES_DIR PATCHES_DIR');
    process.exit(2);
}

const packagesRoot = path.resolve(packagesArg);
const patchesRoot = path.resolve(patchesArg);
const packages = [
    { name: 'core-api', base: '9ded4c6c3bc16303b65f2137879f52ef2969a918' },
    { name: 'ember-core', base: '20262cf26edafaf2f67b052ce5fbba00e5041dad' },
    { name: 'ember-ui', base: '82ccf2634f372405a4ac363b6b9e7bb562cbcc1b' },
    { name: 'fleetops', base: '1d9cb33dd8fa4782ded2bc802c66304aa25a500f' },
    { name: 'ledger', base: '9e23340a962884088de03b0973e23e40f97e35ee' },
];

function git(packageDir, args) {
    return spawnSync('git', ['-C', packageDir, ...args], { encoding: 'utf8' });
}

function assertGitSucceeded(result, message) {
    if (result.error || result.status !== 0) {
        const detail = result.error?.message ?? result.stderr?.trim() ?? 'git returned a non-zero exit code';
        throw new Error(`${message}: ${detail}`);
    }
}

function getPatchExclusions(packageDir, patchFile) {
    const source = fs.readFileSync(patchFile, 'utf8');
    const sections = [...source.matchAll(/^diff --git a\/([^\r\n]+) b\/([^\r\n]+)$/gm)];
    const exclusions = [];

    for (let index = 0; index < sections.length; index += 1) {
        const section = sections[index];
        const relativePath = section[2].replace(/\r$/, '');
        const sectionEnd = sections[index + 1]?.index ?? source.length;
        const patchSection = source.slice(section.index, sectionEnd);
        const isNewFile = patchSection.includes('\nnew file mode ') || patchSection.includes('\n--- /dev/null');

        if (fs.existsSync(path.join(packageDir, relativePath)) || isNewFile) {
            continue;
        }

        // Some upstream pins omit their own test suite. Keep the runtime patch
        // reproducible without making a missing test fixture block production builds.
        if (relativePath.startsWith('tests/')) {
            exclusions.push(`--exclude=${relativePath}`);
            continue;
        }

        throw new Error(`Patch target is missing from ${packageDir}: ${relativePath}`);
    }

    return exclusions;
}

for (const entry of packages) {
    const packageDir = path.join(packagesRoot, entry.name);
    const patchFile = path.join(patchesRoot, `${entry.name}.patch`);

    if (!fs.existsSync(packageDir) || !fs.statSync(packageDir).isDirectory()) {
        throw new Error(`Missing upstream package directory: ${packageDir}`);
    }
    if (!fs.existsSync(patchFile)) {
        throw new Error(`Missing upstream source patch: ${patchFile}`);
    }

    const patchExclusions = getPatchExclusions(packageDir, patchFile);

    // A source archive or Docker COPY may omit the submodule gitfile. Only
    // validate HEAD when this package directory has its own Git metadata.
    if (fs.existsSync(path.join(packageDir, '.git'))) {
        const head = git(packageDir, ['rev-parse', 'HEAD']);
        if (head.status === 0 && head.stdout.trim() !== entry.base) {
            throw new Error(`${entry.name} is pinned to ${head.stdout.trim()}, expected ${entry.base}`);
        }
    }

    const reverseCheck = git(packageDir, ['apply', '--reverse', '--check', ...patchExclusions, patchFile]);
    if (reverseCheck.status === 0) {
        console.log(`Already applied ${entry.name} patch`);
        continue;
    }

    const check = git(packageDir, ['apply', '--check', ...patchExclusions, patchFile]);
    if (check.status === 0) {
        const applied = git(packageDir, ['apply', ...patchExclusions, patchFile]);
        assertGitSucceeded(applied, `Could not apply ${entry.name} patch`);
        console.log(`Applied ${entry.name} patch`);
        continue;
    }

    const reason = check.error?.message ?? check.stderr?.trim() ?? 'patch did not match the package source';
    throw new Error(`Cannot apply ${entry.name} patch to this source tree: ${reason}`);
}
