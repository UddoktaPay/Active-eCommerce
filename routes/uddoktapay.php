<?php

/*
|--------------------------------------------------------------------------
| Uddoktapay Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

//Uddoktapay Start
Route::controller(App\Http\Controllers\Payment\UddoktapayController::class)->group(function () {
    Route::any('/uddoktapay/success', 'success')->name('uddoktapay.success');
    Route::any('/uddoktapay/cancel', 'cancel')->name('uddoktapay.cancel');
    Route::any('/uddoktapay/ipn', 'ipn')->name('uddoktapay.ipn');
});
//Uddoktapay end
