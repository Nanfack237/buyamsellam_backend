<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'store_id',
        'stock_id',
        'quantity',
        'date',
        'selling_price',
        'total_price',
        'customer_id',
        'customer_name',
        'customer_contact',
        'user_id',
        'status'
    ];

    public function products()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stores()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function stocks()
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }
}
