import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve, relative, isAbsolute } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/London',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZoneName: 'short',
});

export function stampPage(html, committedAt) {
    const date = new Date(committedAt);
    if (Number.isNaN(date.getTime())) throw new Error('The website commit has no valid timestamp.');
    const marker = /<time\b(?=[^>]*\bid=["']last-updated["'])[^>]*>[\s\S]*?<\/time>/g;
    if (Array.from(html.matchAll(marker)).length !== 1) {
        throw new Error('Expected exactly one last-updated time element in site/index.html.');
    }
    return html.replace(marker, '<time id="last-updated" datetime="' + date.toISOString() + '">' + formatter.format(date) + '</time>');
}

function main() {
    const args = process.argv.slice(2);
    if (args.length && (args.length !== 2 || args[0] !== '--output')) {
        throw new Error('Usage: node scripts/stamp-site.mjs [--output path]');
    }
    const destination = args.length ? resolve(root, args[1]) : resolve(root, 'site/index.html');
    const location = relative(root, destination);
    if (location.startsWith('..') || isAbsolute(location)) throw new Error('Output must stay inside this checkout.');
    const committedAt = execFileSync('git', ['log', '-1', '--format=%cI', '--', 'site'], {
        cwd: root, encoding: 'utf8',
    }).trim();
    if (!committedAt) throw new Error('No website commit was found. Fetch the repository history before publishing.');
    const html = stampPage(readFileSync(resolve(root, 'site/index.html'), 'utf8'), committedAt);
    mkdirSync(dirname(destination), { recursive: true });
    writeFileSync(destination, html);
    console.log('Stamped website update time from its last commit: ' + formatter.format(new Date(committedAt)));
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) main();
