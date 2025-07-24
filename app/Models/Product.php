<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Store;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Stock;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'image1',
        'image2',
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

    public function sales()
    {
        return $this->hasMany(Sale::class, 'product_id');
    }
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'product_id');
    }
    public function stocks()
    {
        return $this->hasMany(Stock::class, 'product_id');
    }
}
