<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'student_id',
        'lesson_id',
        'makeup_lesson_id',
        'staff_id',
        'status',
        'is_late',
        'makeup_type',
        'note',
    ];

    protected $casts = [
        'is_late' => 'boolean',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function makeupLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'makeup_lesson_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
