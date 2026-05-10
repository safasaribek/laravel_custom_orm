<?php

namespace App\Models;

use App\Orm\Model;
use App\Orm\Relations\BelongsTo;
use App\Orm\Relations\BelongsToMany;

class Post extends Model
{
    protected string $table = 'posts';

    protected array $fillable = ['title', 'body', 'user_id', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }
}
