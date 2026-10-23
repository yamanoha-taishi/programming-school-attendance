<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lesson_plan_id',
        'section_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * @return BelongsTo<LessonPlan, $this>
     */
    public function lessonPlan(): BelongsTo
    {
        return $this->belongsTo(LessonPlan::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * この授業回を補講先（振替・30分前補講）にしている欠席記録。
     *
     * @return HasMany<Attendance, $this>
     */
    public function makeupAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'makeup_lesson_id');
    }
}
