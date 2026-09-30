<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /** 学年を低い順に並べたもの。学年順の並べ替えに使う */
    public const GRADES = ['年中', '年長', '小1', '小2', '小3', '小4', '小5', '小6', '中1', '中2', '中3'];

    protected $fillable = [
        'guardian_id',
        'school_class_id',
        'section_id',
        'grade',
        'gender',
        'name',
        'name_kana',
        'note',
        'leave_from',
        'leave_until',
    ];

    protected $casts = [
        'leave_from' => 'date',
        'leave_until' => 'date',
    ];

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
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
     * 学年の並び順（年中が0、中3が10）を返す。
     * GRADESに無い学年の場合は、一番後ろに並ぶよう最大値を返す。
     */
    public function gradeOrder(): int
    {
        $index = array_search($this->grade, self::GRADES, true);

        return $index === false ? PHP_INT_MAX : $index;
    }

    /**
     * 指定した日に休会中ではない生徒に絞り込む。
     * 休会開始日が無い／まだ来ていない／休会終了日が過ぎている、のいずれかなら休会中ではない。
     *
     * @param  Builder<Student>  $query
     */
    public function scopeNotOnLeave(Builder $query, string $date): void
    {
        $query->where(function (Builder $query) use ($date) {
            $query->whereNull('leave_from')
                ->orWhereDate('leave_from', '>', $date)
                ->orWhereDate('leave_until', '<', $date);
        });
    }
}
