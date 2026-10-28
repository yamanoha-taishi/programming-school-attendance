# CLAUDE.md

子供向けプログラミング教室の出席管理アプリ（ポートフォリオ用）。運営スタッフと保護者が使う。スマホ対応を優先する。

## フォルダ構成

- `web/`：Laravel + Inertia + React のソース一式
- `docs/design/`：設計書（要件定義書・テーブル定義書・画面一覧・遷移図・ルーティング設計・デザイントークン定義書・モックアップ）
- `docs/guides/`：実装ロードマップ・環境構築手順書・開発コマンド一覧
- `memo/`：作業メモ（Git管理対象外）

仕様で迷ったら、まず `docs/` の該当ドキュメントを読む。推測で決めずに、書かれていなければ確認する。

## 技術構成

- Laravel 13（PHP 8.4）、Inertia.js + React 19（TypeScript）、Tailwind 4
- PostgreSQL 16（ローカルはDocker、本番はNeon）
- 認証は `guardian`（保護者）と `staff`（スタッフ）の2つのguard。会員番号（member_code）＋パスワードでログインする
- スターターキット由来の `User` モデル・`users` テーブル・`web` ガードは使っていない（整理は別PRで行うので、今は触らない）

## コマンド

- コマンドは `web/` フォルダで実行する
- PHP・Laravelは `app` コンテナ、npmは `node` コンテナで実行する。**Windows側で直接 `npm` や `php` を実行しない**（パッケージはコンテナの中にしか無い）

```bash
docker compose exec app php artisan test                      # テスト
docker compose exec app php artisan test --filter=クラス名     # テストの絞り込み
docker compose exec app php artisan route:list --name=admin.attendances
```

- `composer ci:check` は `app` コンテナに npm が無いため失敗する。ローカルでは使わない
- `php artisan migrate:fresh --seed` はDBのデータが全部消えるので、実行する前に確認を取る

## 変更したあとの確認（全部通るまで直す）

`web/` フォルダで次を実行する。自動で直せるもの（Pint・Prettier・ESLint）を直してから、PHPStan・テスト・型チェックまでまとめて確認する。

```bash
bash scripts/check.sh          # PHP・フロント両方
bash scripts/check.sh php      # PHPだけ変更したとき
bash scripts/check.sh front    # フロントだけ変更したとき
```

最後に「✓ すべて通りました」と出れば完了。失敗したものは最後に一覧で出るので、直してからもう一度実行する。

## 用語

- 「クラス」ではなく **授業クラス**（school_classes）。「授業実施単位」（sections）、「授業回」（lessons）、「授業内容」（lesson_plans）と区別する
- 基礎クラス＝クラスA（A1〜A3）、応用クラス＝クラスB（B1〜B6）。内部コードはハイフンなし（`A1`、`B3`）
- 振替・30分前補講で、補講先の授業回に来る生徒を「来る側」と呼ぶ

## 実装上の決まり

- 出席ステータス（6種類）の判定順は、テーブル定義書の `attendances` の「画面表示ステータスとの対応」に従う
- 補講先での出欠（`makeup_status`）は、補講先に別レコードを作らず、元の欠席レコードに持たせる
- 出席記録の保存は `updateOrCreate()`（student_id・lesson_idの複合キー）。削除ルートは作らない。出欠を未記録に戻す操作も作らない
- ルート・コントローラー・Inertiaページの対応は `docs/design/ルーティング設計.md` に従う。新しいルートを足すときは、このドキュメントも更新する

## デザインの決まり

詳しくは `docs/design/デザイントークン定義書.md`。特に間違えやすいもの：

- オレンジは `brand-accent` 系を使う。`bg-accent` はスターターキットのホバー用の薄いグレーなので別物
- 淡い背景（`-bg`、`-tint`）の上に文字を置くときは、濃い文字色（`-text`）を使う（コントラスト比のため）
- フォームのエラーは `bg-status-absent-bg` `border-status-absent` `text-status-absent-text` のバナーにする。`text-destructive` は使わない
- 状態は色だけで示さず、必ずラベルの文字と一緒に出す
- ライトモードのみ。Webフォントは使わない
- ボタン・入力欄の高さは44px（`h-11`）が基準

## ドキュメントを更新するとき

- 冒頭の「更新日：」は日付だけ書く。変更内容の説明は付けない
- 文章は平易な日本語で書く
