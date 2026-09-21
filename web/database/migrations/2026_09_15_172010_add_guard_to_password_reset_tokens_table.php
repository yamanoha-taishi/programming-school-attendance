<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->enum('guard', ['guardian', 'staff']);
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropPrimary();
            $table->primary(['email', 'guard']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn('guard');
            $table->primary('email');
        });
    }
};
