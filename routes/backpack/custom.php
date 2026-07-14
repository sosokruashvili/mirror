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
    Route::get('dashboard/orders-area-chart', 'DashboardController@getOrdersAreaChart')->name('dashboard.ordersAreaChart');
    Route::crud('order', 'OrderCrudController');
    Route::post('order/bulk-delete', 'OrderCrudController@bulkDelete')->name('order.bulkDelete');
    Route::post('order/calculate-service-price', 'OrderCrudController@calculate_order_service_price')->name('order.calculateServicePrice');
    Route::post('order/{id}/confirm', 'OrderCrudController@confirm')->name('order.confirm');
    Route::post('order/{id}/finish', 'OrderCrudController@finish')->name('order.finish');
    Route::post('order/piece/{id}/stage', 'OrderCrudController@updatePieceStage')->name('order.piece.updateStage');
    Route::crud('user', 'UserCrudController');
    Route::crud('client', 'ClientCrudController');
    Route::post('client/create-ajax', 'ClientCrudController@createAjax')->name('client.createAjax');
    Route::crud('role', 'RoleCrudController');
    Route::crud('permission', 'PermissionCrudController');
    Route::crud('product', 'ProductCrudController');
    Route::crud('piece', 'PieceCrudController');
    Route::crud('service', 'ServiceCrudController');
    Route::get('service/get-extra-fields/{id}', 'ServiceCrudController@getExtraFields')->name('service.getExtraFields');
    Route::crud('stage', 'StageCrudController');
    Route::get('product/get-products-filtered/{product_type}', 'ProductCrudController@getProductsFiltered')->name('products.getProductsFiltered');
    Route::get('product/get-price/{id}', 'ProductCrudController@getProductPrice')->name('product.getPrice');
    Route::crud('payment', 'PaymentCrudController');
    Route::post('payment/create-ajax', 'PaymentCrudController@createAjax')->name('payment.createAjax');
    Route::get('payment/get-payment-stats', 'PaymentCrudController@getPaymentStats')->name('payment.getPaymentStats');
    Route::get('payment/get-client-balance/{clientId}', 'PaymentCrudController@getClientBalance')->name('payment.getClientBalance');
    Route::get('order/get-orders-by-client/{clientId}', 'OrderCrudController@getOrdersByClient')->name('order.getOrdersByClient');
    Route::get('order/{id}/invoice', 'OrderCrudController@invoice')->name('order.invoice');
    Route::crud('warehouse', 'WarehouseCrudController');
    Route::crud('warehouse-expense', 'WarehouseExpenseCrudController');
    Route::crud('client-balance', 'ClientBalanceCrudController');
    Route::get('client-balance/get-balance-stats', 'ClientBalanceCrudController@getBalanceStats')->name('client-balance.getBalanceStats');
    Route::post('client-balance/recalculate', 'ClientBalanceCrudController@recalculate')->name('client-balance.recalculate');
    Route::crud('custom-price', 'CustomPriceCrudController');
    Route::crud('cashier', 'CashierCrudController');
    Route::crud('cashier-expense', 'CashierExpenseCrudController');
    
    // Global settings page
    Route::get('settings', 'SettingController@edit')->name('settings.edit');
    Route::put('settings', 'SettingController@update')->name('settings.update');
    Route::post('settings/sync-from-prod', 'SettingController@syncFromProd')->name('settings.syncFromProd');

    // Team order processing page
    Route::get('team/orders', 'TeamOrderController@index')->name('team.orders');
    Route::get('team/orders/check', 'TeamOrderController@check')->name('team.orders.check');
    Route::post('team/orders/{id}/finish', 'TeamOrderController@finish')->name('team.orders.finish');
    Route::post('team/orders/{id}/archive', 'TeamOrderController@archive')->name('team.orders.archive');
    Route::post('team/orders/{id}/unarchive', 'TeamOrderController@unarchive')->name('team.orders.unarchive');
    Route::post('team/pieces/{id}/broken', 'TeamOrderController@markPieceBroken')->name('team.pieces.broken');
    Route::post('team/pieces/{id}/stage', 'TeamOrderController@updatePieceStage')->name('team.pieces.stage');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
