// 実行の前：危ない操作は止めて、ユーザーに確認する（ask）
//   - php artisan migrate:fresh
//   - git push の強制プッシュ（--force、-f、--force-with-lease、+ブランチ）
//   - main への git push
//   - .env の書き換え（.env.example などは対象外）
import path from 'node:path';
import { printJson, readInput, run } from './lib.mjs';

const input = readInput();
const tool = input.tool_name ?? '';
const ti = input.tool_input ?? {};

function ask(reason) {
    printJson({
        hookSpecificOutput: {
            hookEventName: 'PreToolUse',
            permissionDecision: 'ask',
            permissionDecisionReason: reason,
        },
    });
    process.exit(0);
}

// Edit・Write：.env そのものを書き換えるとき
if (['Edit', 'Write', 'MultiEdit', 'NotebookEdit'].includes(tool)) {
    const file = ti.file_path ?? ti.notebook_path ?? '';
    if (path.basename(file) === '.env') ask('.env を書き換えようとしています');
    process.exit(0);
}

if (tool !== 'Bash' && tool !== 'PowerShell') process.exit(0);
const command = String(ti.command ?? '');

// ; && || | 改行 で区切って、1つずつ見る
const segments = command.split(/&&|\|\||[;|\n]/).map((s) => s.trim());

const envFile = /(^|[\s'"/\\=:])\.env(?![\w.-])/;
const writes =
    />|\btee\b|\bsed\b[^]*\s-i|\bperl\b[^]*\s-i|\b(cp|mv|rm|truncate|touch|dd)\b|Set-Content|Add-Content|Out-File|Remove-Item|Copy-Item|Move-Item|New-Item|WriteAllText/i;

function currentBranch() {
    const r = run('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { cwd: input.cwd || process.cwd() });
    return r.code === 0 ? r.stdout.trim() : '';
}

for (const seg of segments) {
    if (/\bmigrate:fresh\b/.test(seg)) {
        ask('migrate:fresh は DB のデータを全部消します');
    }

    if (/\bkey:generate\b/.test(seg) && !/--show\b/.test(seg)) {
        ask('key:generate は .env の APP_KEY を書き換えます');
    }

    if (envFile.test(seg) && writes.test(seg)) {
        ask('.env を書き換えるコマンドのようです');
    }

    const push = seg.match(/\bgit\b.*?\bpush\b(.*)$/);
    if (push) {
        const args = push[1].split(/\s+/).filter(Boolean);
        const force = args.some(
            (a) =>
                a === '--force' ||
                a.startsWith('--force-with-lease') ||
                a === '--force-if-includes' ||
                /^-[a-zA-Z]*f[a-zA-Z]*$/.test(a) ||
                /^\+/.test(a),
        );
        if (force) ask('強制プッシュ（--force など）です');

        const toMain = args.some((a) => /^(\+?(refs\/heads\/)?main|.*:(refs\/heads\/)?main)$/.test(a));
        if (toMain) ask('main へのプッシュです');

        if (currentBranch() === 'main') ask('今のブランチが main なので、main へのプッシュになります');
    }
}

process.exit(0);
