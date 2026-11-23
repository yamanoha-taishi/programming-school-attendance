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
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('makeup_status', ['出席', '欠席'])->nullable()->after('makeup_type');
            // 補講先での出欠を記録したスタッフ（staff_idは欠席を記録したスタッフなので、別に持つ）
            $table->foreignId('makeup_staff_id')->nullable()->after('makeup_status')->constrained('staff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('makeup_staff_id');
            $table->dropColumn('makeup_status');
        });
    }
};
