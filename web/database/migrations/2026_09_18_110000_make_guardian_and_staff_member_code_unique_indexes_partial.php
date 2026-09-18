<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * guardians.member_code / staff.member_codeの一意制約も、emailと同様に
     * 論理削除（SoftDeletes）済みの行を除外していなかった。そのため、
     * 退会済みのアカウントが使っていた会員番号を、新しいアカウントに
     * 発行できなくなってしまう問題があった。deleted_atがNULLの行だけを
     * 対象にした部分ユニークインデックスに置き換えることで、論理削除済みの
     * 会員番号は再利用できるようにする。
     */
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropUnique(['member_code']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['member_code']);
        });

        DB::statement('CREATE UNIQUE INDEX guardians_member_code_unique ON guardians (member_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX staff_member_code_unique ON staff (member_code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS guardians_member_code_unique');
        DB::statement('DROP INDEX IF EXISTS staff_member_code_unique');

        Schema::table('guardians', function (Blueprint $table) {
            $table->unique('member_code');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->unique('member_code');
        });
    }
};
