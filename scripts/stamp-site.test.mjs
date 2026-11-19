import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { dirname, resolve, join, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { stampPage } from './stamp-site.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const template = '<footer>Updated <time id="last-updated" datetime="2020-01-01">Old date</time></footer>';

test('London update labels reflect summer and winter time', () => {
    assert.match(stampPage(template, '2026-07-20T12:34:00Z'), /13:34 BST/);
    assert.match(stampPage(template, '2026-11-19T15:50:00Z'), /15:50 GMT/);
    assert.match(stampPage(template, '2026-07-20T12:34:00Z'), /datetime="2026-07-20T12:34:00.000Z"/);
    assert.throws(() => stampPage('<footer>No marker</footer>', '2026-11-19T15:50:00Z'), /exactly one/);
});

test('publication uses the latest website commit, including CSS edits, and ignores documentation-only commits', () => {
    const owned = resolve(root, '.deploy/stamp-tests');
    mkdirSync(owned, { recursive: true });
    const fixture = mkdtempSync(join(owned, 'fixture-'));
    try {
        mkdirSync(join(fixture, 'scripts'));
        mkdirSync(join(fixture, 'site/css'), { recursive: true });
        mkdirSync(join(fixture, '.githooks'));
        copyFileSync(join(root, 'scripts/stamp-site.mjs'), join(fixture, 'scripts/stamp-site.mjs'));
        writeFileSync(join(fixture, 'site/index.html'), template);
        const git = (args, date) => execFileSync('git', args, {
            cwd: fixture, encoding: 'utf8',
            env: date ? { ...process.env, GIT_AUTHOR_DATE: date, GIT_COMMITTER_DATE: date } : process.env,
        });
        git(['init', '--quiet', '--initial-branch=main']);
        git(['config', 'user.name', 'Website timestamp test']);
        git(['config', 'user.email', 'timestamp-test@example.invalid']);
        git(['config', 'core.hooksPath', join(fixture, '.githooks')]);
        git(['add', 'site/index.html']);
        git(['commit', '--quiet', '-m', 'Website'], '2026-07-20T12:34:00Z');
        writeFileSync(join(fixture, 'README.md'), 'Documentation only');
        git(['add', 'README.md']);
        git(['commit', '--quiet', '-m', 'Documentation'], '2026-11-19T15:45:00Z');
        const publish = () => {
            execFileSync(process.execPath, ['scripts/stamp-site.mjs', '--output', 'preview.html'], { cwd: fixture });
            return readFileSync(join(fixture, 'preview.html'), 'utf8');
        };
        const first = publish();
        assert.match(first, /2026-07-20T12:34:00.000Z/);
        assert.equal(publish(), first, 'Re-running publication must not invent a newer update time.');
        writeFileSync(join(fixture, 'site/css/style.css'), 'body { color: green; }');
        git(['add', 'site/css/style.css']);
        git(['commit', '--quiet', '-m', 'CSS change'], '2026-11-19T15:50:00Z');
        assert.match(publish(), /2026-11-19T15:50:00.000Z/);
        assert.match(publish(), /15:50 GMT/);
        assert.equal(readFileSync(join(fixture, 'site/index.html'), 'utf8'), template, 'A preview output must not modify its source.');
    } finally {
        assert.equal(dirname(resolve(fixture)), owned);
        assert.ok(basename(fixture).startsWith('fixture-'));
        rmSync(fixture, { recursive: true, force: true });
    }
});
