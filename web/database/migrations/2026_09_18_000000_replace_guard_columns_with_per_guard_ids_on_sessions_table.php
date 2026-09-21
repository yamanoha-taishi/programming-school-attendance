<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['guard', 'auth_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('guardian_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('staff_id')->nullable()->after('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['guardian_id', 'staff_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->string('guard')->nullable()->after('user_id');
            $table->unsignedBigInteger('auth_id')->nullable()->after('guard');
        });
    }
};
