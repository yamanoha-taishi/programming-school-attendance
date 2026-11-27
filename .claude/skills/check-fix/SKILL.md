---
name: check-fix
description: web フォルダで bash scripts/check.sh を実行し、失敗を読んで直し、「✓ すべて通りました」になるまで繰り返す。「チェックして直して」「check.sh を通して」と言われたとき、または変更を終えたあとに使う。
---

# チェックと修正

`bash scripts/check.sh` を通るまで回す。

## 手順

1. **範囲を決める**。`git status --short -- web` で変更を見る
   - PHP だけ → `php`、フロント（`resources/js`・`resources/css` など）だけ → `front`、両方・わからない → 引数なし
   - ユーザーが範囲を言ったらそれに従う
2. **実行する**（`web` フォルダで。自動で直す版）
   ```bash
   cd web && bash scripts/check.sh [php|front]
   ```
   - 「コンテナが起動していません」と出たら、`docker compose up -d` を実行してからやり直す
   - 全部で数分かかる。タイムアウトは長め（10分）にする
3. **最後の行を見る**
   - 「✓ すべて通りました」→ 完了。何を直したかを短く報告する
   - 「✗ 失敗したもの」→ 一覧の項目ごとに、その上の出力を読んで原因を直す
4. **直す**
   - PHPStan・型チェック：エラーの出たファイルと行を開いて直す。`@phpstan-ignore` や `any`、`// @ts-ignore` でごまかさない
   - テスト（PHP・画面）：まずテストの期待が仕様（`docs/design/`）どおりかを確かめる。コードが間違っていればコードを直す。テストのほうが古ければ、理由を説明してからテストを直す
   - 1つの項目だけ確かめたいときは、個別のコマンドを使ってよい（例：`docker compose exec -T app php artisan test --filter=クラス名`、`docker compose exec -T node npm run types:check`）
5. **2 に戻る**。最後はかならず check.sh 全体で通ったことを確かめる

## 止めるとき

- 同じ失敗が3回続いて直せないときは、そこで止めて、失敗の内容・試したこと・考えられる原因を報告する
- 仕様がわからず直し方が決められないときは、推測で決めずにユーザーに聞く
- `migrate:fresh` など、データが消えるコマンドは使わない
