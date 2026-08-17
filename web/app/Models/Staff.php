<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use SoftDeletes;

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
