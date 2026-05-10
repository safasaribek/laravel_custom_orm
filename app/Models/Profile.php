<?php

namespace App\Models;

use App\Orm\Model;
use App\Orm\Relations\BelongsTo;

class Profile extends Model
{
    protected string $table = 'profiles';

    protected array $fillable = ['user_id', 'bio', 'avatar'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
