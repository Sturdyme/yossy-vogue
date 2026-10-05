<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'brand',
        'stock',
        'image_url',
        'is_featured',
        'is_deal'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_deal' => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');    
    }
}
