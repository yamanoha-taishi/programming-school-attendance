<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sessions.user_id はLaravelの既定スキャフォールドが持つ「webガード
     * （Userモデル）でログインした利用者のID」を記録するカラムだが、
     * このアプリはguardian・staffガードでしかログインせず、webガードは
     * 実際には使われていない。
     *
     * にもかかわらず、セッション保存処理（DatabaseSessionHandler）は
     * 「その時点のデフォルトガード」のIDを無条件にuser_idへ書き込む実装
     * になっており、auth:guardian,staffミドルウェアが認証成功時に
     * デフォルトガードをguardian・staffへ切り替えるため、本来usersテーブル
     * のIDを指すべきuser_idにguardian・staffのIDが紛れ込んでいた。
     *
     * webガードを使う予定が今のところ無いため、混入元となる列自体を
     * 削除する（GuardAwareDatabaseSessionHandler側もparentの
     * addUserInformation()を呼ばないよう修正済み）。
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->index();
        });
    }
};
