<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShadeColor extends Model
{
    use HasFactory;

    protected $fillable = [
        'colorcode',
        'colorname',
        'rvalue',
        'gvalue',
        'bvalue',
        'created_at',
        'updated_at'
    ];

    protected $table = 'shade_colors';

    // If you have any specific casting or dates
    protected $casts = [
        'rvalue' => 'integer',
        'gvalue' => 'integer',
        'bvalue' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
