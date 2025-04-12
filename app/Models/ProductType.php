<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_type_name',
        'product_type_description',
    ];

    /**
     * Get the products associated with the product type.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'product_type_id', 'product_type_id');
    }
}
