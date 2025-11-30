<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'checked_out_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'checked_out_at' => 'datetime',
    ];
}
