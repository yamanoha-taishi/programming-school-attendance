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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('lesson_id')->constrained();
            $table->foreignId('makeup_lesson_id')->nullable()->constrained('lessons');
            $table->foreignId('staff_id')->nullable()->constrained('staff');
            $table->enum('status', ['出席', '欠席'])->nullable();
            $table->boolean('is_late')->default(false);
            $table->enum('makeup_type', ['振替', '30分前補講', '補講なし', '未定'])->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'lesson_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
