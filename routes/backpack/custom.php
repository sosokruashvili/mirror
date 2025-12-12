<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('order', 'OrderCrudController');
    Route::post('order/bulk-delete', 'OrderCrudController@bulkDelete')->name('order.bulkDelete');
    Route::post('order/calculate-service-price', 'OrderCrudController@calculate_order_service_price')->name('order.calculateServicePrice');
    Route::post('order/{id}/confirm', 'OrderCrudController@confirm')->name('order.confirm');
    Route::crud('user', 'UserCrudController');
    Route::crud('client', 'ClientCrudController');
    Route::crud('role', 'RoleCrudController');
    Route::crud('product', 'ProductCrudController');
    Route::crud('piece', 'PieceCrudController');
    Route::crud('service', 'ServiceCrudController');
    Route::get('service/get-extra-fields/{id}', 'ServiceCrudController@getExtraFields')->name('service.getExtraFields');
    Route::get('product/get-products-filtered/{product_type}', 'ProductCrudController@getProductsFiltered')->name('products.getProductsFiltered');
    Route::get('product/get-price/{id}', 'ProductCrudController@getProductPrice')->name('product.getPrice');
    Route::crud('payment', 'PaymentCrudController');
    Route::get('payment/get-payment-stats', 'PaymentCrudController@getPaymentStats')->name('payment.getPaymentStats');
    Route::get('order/get-orders-by-client/{clientId}', 'OrderCrudController@getOrdersByClient')->name('order.getOrdersByClient');
    Route::crud('warehouse', 'WarehouseCrudController');
    Route::crud('client-balance', 'ClientBalanceCrudController');
    Route::get('client-balance/get-balance-stats', 'ClientBalanceCrudController@getBalanceStats')->name('client-balance.getBalanceStats');
    Route::crud('custom-price', 'CustomPriceCrudController');
    
    // Team order processing page
    Route::get('team/orders', 'TeamOrderController@index')->name('team.orders');
    Route::post('team/orders/{id}/finish', 'TeamOrderController@finish')->name('team.orders.finish');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
