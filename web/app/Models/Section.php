<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use SoftDeletes;

    /**
     * 応用クラス（B1〜B6）が共用する授業実施単位の名前。要件定義書3章の「B1〜B6の授業実施単位は名称を『B』で統一する」に対応する。
     */
    public const ADVANCED_NAME = 'B';

    protected $fillable = [
        'name',
        'weekday',
        'start_time',
        'end_time',
    ];

    /**
     * @return HasMany<Holiday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * 応用クラスの授業実施単位か。
     * 応用クラスはB1〜B6が同じ時間に一緒に授業をし、生徒が自分のペースで授業を進めるため、
     * 補講先の候補などで基礎クラスと扱いを分ける。
     */
    public function isAdvanced(): bool
    {
        return $this->name === self::ADVANCED_NAME;
    }
}
