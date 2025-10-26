<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Product;

Route::get('/', function () {
    return redirect('/admin');
});

// API route for filtered products
Route::get('/api/products-filtered', function (Request $request) {
    $search = $request->input('q');
    $orderProductType = $request->input('order_product_type');
    
    $query = Product::query();

    if ($orderProductType == 'lamix') {
        $query->where('product_type', 'glass');
        $query->orWhere('product_type', 'film');
    } else if ($orderProductType == 'glass_pkg') {
        $query->where('product_type', 'glass');
        $query->orWhere('product_type', 'butyl');
    } else if ($orderProductType == 'glass') {
        $query->where('product_type', 'glass');
    } else if ($orderProductType == 'mirror') {
        $query->where('product_type', 'mirror');
    }
    
    // Search by title if search term provided
    if ($search) {
        $query->where('title', 'LIKE', "%{$search}%");
    }
    
    $products = $query->limit(10)->get();
    
    return $products->map(function ($product) {
        return [
            'id' => $product->id,
            'text' => $product->title,
        ];
    });
});
