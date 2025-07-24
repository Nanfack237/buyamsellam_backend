<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Store;

class LoginLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'ip_address',
        'user_agent',
        'login_time',
        'date'
    ];


    public function stores()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
