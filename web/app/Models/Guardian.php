<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'member_code',
        'name',
        'name_kana',
        'email',
        'password',
        'note',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
