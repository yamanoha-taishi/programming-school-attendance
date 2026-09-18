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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained();
            $table->foreignId('school_class_id')->constrained();
            $table->foreignId('section_id')->constrained();
            $table->enum('grade', ['年中', '年長', '小1', '小2', '小3', '小4', '小5', '小6', '中1', '中2', '中3']);
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('name');
            $table->string('name_kana');
            $table->text('note')->nullable();
            $table->date('leave_from')->nullable();
            $table->date('leave_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
