<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * デフォルトのsessionsテーブルではuser_idにインデックスが張られているが、
     * 実際にこのアプリで使っているguardian_id・staff_idには索引が無いまま
     * だった。パスワードリセット・パスワード変更時の「同一アカウントの
     * 他セッションを削除する」処理（WHERE guardian_id = ? / WHERE staff_id = ?）
     * が全表走査になってしまうため、索引を追加する。
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->index('guardian_id');
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['guardian_id']);
            $table->dropIndex(['staff_id']);
        });
    }
};
