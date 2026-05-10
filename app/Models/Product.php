<?php

namespace App\Models;

use App\Orm\Model;

class Product extends Model
{
    protected string $table = 'products';

    protected array $fillable = ['name', 'price', 'stock'];
}
