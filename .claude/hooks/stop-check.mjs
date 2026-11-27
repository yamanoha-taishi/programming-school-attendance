// 返事をする前：web に変更があれば bash scripts/check.sh --check を実行する
//   - 失敗したら exit 2 で結果を Claude に渡し、直してから終わらせる
//   - 変更が無いとき・前回通ったときと同じ状態のときは実行しない
//   - 同じ会話で3回続けて失敗したら、止めずにユーザーに知らせる
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { isRunning, printJson, readInput, repoDir, run, stateDir, webDir } from './lib.mjs';

const MAX_RETRY = 3;
const input = readInput();

const git = (...args) => run('git', args, { cwd: repoDir });

// web の中の変更されたファイル（未追跡も含む）
const status = git('status', '--porcelain', '--untracked-files=all', '--', 'web');
const changed = status.stdout
    .split('\n')
    .filter(Boolean)
    .map((l) => l.slice(3).replace(/^.* -> /, '').replace(/^"|"$/g, ''));
if (changed.length === 0) process.exit(0);

const isPhp = (f) => /\.php$|(^|\/)(composer\.(json|lock)|phpstan[^/]*|phpunit\.xml|pint\.json)$/.test(f);
const isFront = (f) =>
    /^web\/resources\/(js|css)\/|^web\/(package(-lock)?\.json|tsconfig[^/]*|vite[^/]*|vitest[^/]*|eslint[^/]*|\.prettier[^/]*)$/.test(
        f,
    );
const php = changed.some(isPhp);
const front = changed.some(isFront);
if (!php && !front) process.exit(0);
const target = php && front ? [] : [php ? 'php' : 'front'];

// 今の変更の状態をハッシュにする（同じ状態で何度も実行しないため）
const hash = crypto.createHash('sha256');
hash.update(git('diff', 'HEAD', '--no-ext-diff', '--', 'web').stdout);
for (const f of git('ls-files', '--others', '--exclude-standard', '--', 'web').stdout.split('\n').filter(Boolean)) {
    hash.update(f);
    try {
        hash.update(fs.readFileSync(path.join(repoDir, f)));
    } catch {}
}
const digest = hash.digest('hex');

fs.mkdirSync(stateDir, { recursive: true });
const stateFile = path.join(stateDir, 'stop-check.json');
let state = {};
try {
    state = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
} catch {}
const save = () => fs.writeFileSync(stateFile, JSON.stringify(state, null, 2));

if (state.passed === digest) process.exit(0);

for (const s of ['app', 'node']) {
    if (!isRunning(s)) {
        printJson({ systemMessage: `${s} コンテナが止まっているため、終わる前のチェック（check.sh）を飛ばしました。` });
        process.exit(0);
    }
}

const session = input.session_id ?? 'unknown';
if (state.session !== session) {
    state.session = session;
    state.failures = 0;
}

const r = run('bash', ['scripts/check.sh', '--check', ...target], { cwd: webDir, maxBuffer: 64 * 1024 * 1024 });

if (r.code === 0) {
    state.passed = digest;
    state.failures = 0;
    save();
    process.exit(0);
}

state.failures = (state.failures ?? 0) + 1;
save();

if (state.failures > MAX_RETRY) {
    state.failures = 0;
    save();
    printJson({
        systemMessage: `check.sh が${MAX_RETRY}回続けて失敗しました。自動では止めないので、結果を確認してください。`,
    });
    process.exit(0);
}

// 失敗した項目（「▶ 名前」から次の「▶」まで）の出力だけを渡す
// eslint-disable-next-line no-control-regex
const out = r.out.replace(/\x1b\[[0-9;]*m/g, '');
const failedLabels = (out.split('✗ 失敗したもの：')[1] ?? '')
    .split('\n')
    .map((l) => l.replace(/^\s*-\s*/, '').trim())
    .filter(Boolean);
const sections = out.split(/^▶ /m).slice(1);
const details = sections
    .filter((s) => failedLabels.includes(s.split('\n')[0].trim()))
    .map((s) => {
        const lines = s.trimEnd().split('\n');
        return `▶ ${lines[0]}\n${lines.slice(1).slice(-80).join('\n')}`;
    })
    .join('\n\n');

const cmd = ['bash scripts/check.sh', ...target].join(' ');
process.stderr.write(
    `終わる前のチェック（${cmd} --check）が失敗しました（${state.failures}/${MAX_RETRY}回目）。\n` +
        `直してから終えてください。整形の失敗なら web フォルダで ${cmd} を実行すると自動で直せます。\n\n` +
        (details || out.split('\n').slice(-80).join('\n')),
);
process.exit(2);
