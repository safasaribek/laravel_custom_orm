<?php

namespace App\Models;

use App\Orm\Model;
use App\Orm\Relations\HasMany;
use App\Orm\Relations\HasOne;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = ['name', 'email', 'status', 'age'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}
