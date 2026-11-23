import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// 画面（React）のテストの設定。
// vite.config.ts は Laravel・Wayfinder のプラグイン（php artisan を呼ぶ）を使うため、テストでは使わず、
// React と「@/」の読み替えだけを設定する
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        // ブラウザの代わり。jsdom より読み込むファイルが少なく、テストの準備が速い
        environment: 'happy-dom',
        // テストを動かす作業用のプロセスを、別プロセス（forks）ではなくスレッドで作る（起動が軽い）。
        // Windows のフォルダを Docker から読むとファイルの読み込みが遅く、
        // 別プロセスだと起動の待ち時間（60秒）を超えて、テストが始まらないことがあるため
        pool: 'threads',
        // テストのファイルごとに作業用のスレッドを作り直さず、1つを使い回す。
        // ライブラリの読み込みが1回で済み、ファイルが増えても準備の時間が増えない
        isolate: false,
        maxWorkers: 1,
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
