<?php

namespace App\Models;

use App\Orm\Model;
use App\Orm\Relations\BelongsToMany;

class Tag extends Model
{
    protected string $table = 'tags';

    protected array $fillable = ['name'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
    }
}
