<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Hidden(['password'])]
class Guardian extends Authenticatable
{
    use HasFactory, SoftDeletes;

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
