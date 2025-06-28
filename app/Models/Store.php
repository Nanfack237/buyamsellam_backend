<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'location',
        'contact',
        'image',
        'closing_time',
        'daily_summary',
        'user_id',
        'status'
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
