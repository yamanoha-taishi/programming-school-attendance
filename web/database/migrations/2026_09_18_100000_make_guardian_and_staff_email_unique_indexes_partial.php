<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * guardians.email / staff.emailの一意制約は、論理削除（SoftDeletes）済みの
     * 行を除外していなかった。そのため、退会済みのアカウントが使っていた
     * メールアドレスを、別の新しいアカウントが二度と使えなくなってしまう
     * 問題があった。deleted_atがNULLの行だけを対象にした部分ユニーク
     * インデックスに置き換えることで、論理削除済みのメールアドレスは
     * 再利用できるようにする。
     */
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        DB::statement('CREATE UNIQUE INDEX guardians_email_unique ON guardians (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX staff_email_unique ON staff (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS guardians_email_unique');
        DB::statement('DROP INDEX IF EXISTS staff_email_unique');

        Schema::table('guardians', function (Blueprint $table) {
            $table->unique('email');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
