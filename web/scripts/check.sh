#!/usr/bin/env bash
#
# 変更したあとの確認をまとめて実行する。
#
#   使い方（web フォルダで実行）：
#     bash scripts/check.sh          自動で直す → 確認する（PHP・フロント両方）
#     bash scripts/check.sh --check  直さずに確認だけする
#     bash scripts/check.sh php      PHP側だけ
#     bash scripts/check.sh front    フロント側だけ
#
# 1つ失敗しても最後まで実行し、最後に失敗したものをまとめて表示する。

set -u

# どこから実行しても web フォルダで動くようにする
cd "$(dirname "$0")/.." || exit 1

fix=true
target=all
for arg in "$@"; do
    case "$arg" in
        --check) fix=false ;;
        php | front) target="$arg" ;;
        *)
            echo "不明な引数です：$arg"
            exit 2
            ;;
    esac
done

# コンテナが起動しているか確認する
for service in app node; do
    if [ -z "$(docker compose ps --status running -q "$service" 2>/dev/null)" ]; then
        echo "$service コンテナが起動していません。先に docker compose up -d を実行してください。"
        exit 1
    fi
done

failed=()

# -T：Claude Code など、ターミナルが無い環境からでも実行できるようにする
run() {
    local label="$1"
    shift
    echo ""
    echo "▶ $label"
    if ! docker compose exec -T "$@"; then
        failed+=("$label")
    fi
}

if [ "$target" = all ] || [ "$target" = php ]; then
    if [ "$fix" = true ]; then
        run "Pint（自動で直す）" app composer lint
    fi
    run "Pint（確認）" app composer lint:check
    run "PHPStan" app composer types:check
    run "テスト" app php artisan test
fi

if [ "$target" = all ] || [ "$target" = front ]; then
    if [ "$fix" = true ]; then
        run "Prettier（自動で直す）" node npm run format
        run "ESLint（自動で直す）" node npm run lint
    fi
    run "Prettier（確認）" node npm run format:check
    run "ESLint（確認）" node npm run lint:check
    run "型チェック" node npm run types:check
fi

echo ""
if [ ${#failed[@]} -eq 0 ]; then
    echo "✓ すべて通りました"
    exit 0
fi

echo "✗ 失敗したもの："
for label in "${failed[@]}"; do
    echo "  - $label"
done
exit 1
