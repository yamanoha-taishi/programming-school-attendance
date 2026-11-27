// ファイルを書き換えた直後：そのファイルだけに Prettier・Pint をかける
//   web/resources/js の中 → node コンテナで Prettier
//   web の中の .php       → app コンテナで Pint
import fs from 'node:fs';
import { isRunning, printJson, readInput, relToWeb, run, webDir } from './lib.mjs';

const input = readInput();
const file = input.tool_input?.file_path;
const rel = relToWeb(file);
if (!rel || !fs.existsSync(file)) process.exit(0);

let service, args;
if (rel.startsWith('resources/js/')) {
    service = 'node';
    args = ['npx', 'prettier', '--write', '--ignore-unknown', rel];
} else if (rel.endsWith('.php') && !rel.startsWith('vendor/')) {
    service = 'app';
    args = ['vendor/bin/pint', rel];
} else {
    process.exit(0);
}

// コンテナが止まっていれば何もしない（チェックは終わる前のフックで行う）
if (!isRunning(service)) process.exit(0);

const r = run('docker', ['compose', 'exec', '-T', service, ...args], { cwd: webDir });
if (r.code !== 0) {
    // 構文エラーなどで整形できなかったときは、Claude に知らせる（作業は止めない）
    printJson({
        hookSpecificOutput: {
            hookEventName: 'PostToolUse',
            additionalContext: `${rel} の自動整形に失敗しました：\n${r.out.slice(-2000)}`,
        },
    });
}
