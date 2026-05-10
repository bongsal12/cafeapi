<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningTable extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
