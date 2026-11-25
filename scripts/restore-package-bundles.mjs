import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = path.resolve(process.argv[2] ?? process.cwd());

function git(args, cwd = root) {
    return spawnSync('git', args, { cwd, encoding: 'utf8', windowsHide: true });
}

function runGit(args, cwd = root) {
    const result = git(args, cwd);
    if (result.error || result.status !== 0) {
        throw new Error(result.error?.message ?? result.stderr?.trim() ?? `git ${args.join(' ')} failed`);
    }
    return result.stdout.trim();
}

const submoduleConfig = runGit(['config', '-f', '.gitmodules', '--get-regexp', '^submodule\\..*\\.path$']);
const modulePaths = submoduleConfig
    .split(/\r?\n/)
    .map((line) => line.match(/^submodule\.[^.]+\.path\s+(.+)$/)?.[1])
    .filter(Boolean);

if (modulePaths.length === 0) {
    throw new Error('No package paths were found in .gitmodules.');
}

let restored = 0;
const incompleteHistory = [];

for (const modulePath of modulePaths) {
    const treeEntry = runGit(['ls-tree', 'HEAD', '--', modulePath]);
    const match = treeEntry.match(/^160000 commit ([0-9a-f]{40})\t/);
    if (!match) {
        throw new Error(`Expected ${modulePath} to be a pinned Git submodule in HEAD.`);
    }
    const commit = match[1];
    const bundleName = `${modulePath.replaceAll('/', '_')}.bundle`;
    const bundlePath = path.join(root, 'git-bundles', bundleName);
    const checkoutPath = path.join(root, modulePath);

    if (!fs.existsSync(bundlePath)) {
        throw new Error(`Missing pinned source bundle: ${path.relative(root, bundlePath)}`);
    }

    fs.mkdirSync(path.dirname(checkoutPath), { recursive: true });
    if (fs.existsSync(path.join(checkoutPath, '.git'))) {
        const current = git(['rev-parse', 'HEAD'], checkoutPath);
        if (current.status !== 0 || current.stdout.trim() !== commit) {
            throw new Error(`${modulePath} has existing Git metadata at a different revision; refusing to replace it.`);
        }
        console.log(`Already restored ${modulePath} at ${commit}`);
        continue;
    }

    if (fs.existsSync(checkoutPath) && fs.readdirSync(checkoutPath).length > 0) {
        throw new Error(`${modulePath} is non-empty and has no Git metadata; refusing to overwrite it.`);
    }

    fs.mkdirSync(checkoutPath, { recursive: true });
    runGit(['init', '--quiet', checkoutPath]);
    runGit(['config', 'core.longpaths', 'true'], checkoutPath);

    const fetched = git(['fetch', '--no-tags', bundlePath, commit], checkoutPath);
    const treeAvailable = git(['cat-file', '-e', `${commit}^{tree}`], checkoutPath);
    if (treeAvailable.status !== 0) {
        const detail = fetched.stderr?.trim() || treeAvailable.stderr?.trim() || 'pinned tree is missing';
        throw new Error(`Could not restore ${modulePath}@${commit}: ${detail}`);
    }
    if (fetched.status !== 0) {
        incompleteHistory.push(modulePath);
        console.warn(`Bundle for ${modulePath} omits older history; the pinned source tree is present.`);
    }

    runGit(['-c', 'core.longpaths=true', 'checkout', '--detach', commit], checkoutPath);
    const checkedOut = runGit(['rev-parse', 'HEAD'], checkoutPath);
    if (checkedOut !== commit) {
        throw new Error(`${modulePath} checked out ${checkedOut}; expected ${commit}.`);
    }
    restored += 1;
    console.log(`Restored ${modulePath}@${commit}`);
}

console.log(`Restored ${restored} pinned package snapshot(s).`);
if (incompleteHistory.length > 0) {
    console.warn(`History is incomplete for ${incompleteHistory.length} bundle(s); all pinned trees were verified.`);
}
