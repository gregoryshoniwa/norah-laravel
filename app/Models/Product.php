<?php

namespace App\Models;

use App\Models\ProductType;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_name',
        'product_description',
        'product_price',
        'product_image',
        'product_status',
        'product_type_id',
        'merchant_id',
    ];

    /**
     * Get the product type associated with the product.
     */
    public function productType()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id', 'product_type_id');
    }

    /**
     * Get the merchant associated with the product.
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'merchant_id');
    }
}
