<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Store;
use App\Models\User;

class Employee extends Model
{
    use HasFactory;

    
    protected $fillable = [
        'name',
        'email',
        'password',
        'address',
        'contact',
        'image',
        'role',
        'store_id',
        'user_id',
        'status'
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
