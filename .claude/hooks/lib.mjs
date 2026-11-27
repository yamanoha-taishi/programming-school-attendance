// フックで共通に使う小さな関数
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const repoDir = path.resolve(
    process.env.CLAUDE_PROJECT_DIR ??
        path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..'),
);
export const webDir = path.join(repoDir, 'web');
export const stateDir = path.join(repoDir, '.claude', 'hooks', '.state');

// フックに渡される JSON を読む
export function readInput() {
    try {
        return JSON.parse(fs.readFileSync(0, 'utf8') || '{}');
    } catch {
        return {};
    }
}

export function run(cmd, args, opts = {}) {
    const r = spawnSync(cmd, args, { encoding: 'utf8', ...opts });
    return {
        code: r.status ?? 1,
        out: (r.stdout ?? '') + (r.stderr ?? ''),
        stdout: r.stdout ?? '',
    };
}

// docker compose のサービスが起動しているか
export function isRunning(service) {
    const r = run('docker', ['compose', 'ps', '--status', 'running', '-q', service], {
        cwd: webDir,
    });
    return r.code === 0 && r.stdout.trim() !== '';
}

// web フォルダからの相対パス（スラッシュ区切り）。web の外なら null
export function relToWeb(file) {
    if (!file) return null;
    const rel = path.relative(webDir, path.resolve(file));
    if (rel.startsWith('..') || path.isAbsolute(rel)) return null;
    return rel.split(path.sep).join('/');
}

export function printJson(obj) {
    process.stdout.write(JSON.stringify(obj));
}
