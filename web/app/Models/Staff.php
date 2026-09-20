<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Hidden(['password'])]
class Staff extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory, SoftDeletes;

    /**
     * updateやfill経由でpasswordに平文を代入しても、自動でハッシュ化される。
     * 既にハッシュ化済みの値（Hash::makeで作った値）を代入した場合は
     * 二重ハッシュ化されない（Hash::isHashed()で判定される）。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    protected $fillable = [
        'member_code',
        'name',
        'name_kana',
        'email',
        'password',
        'role',
        'note',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
