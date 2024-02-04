<?php

use Illuminate\Support\Facades\Route;
use App\Models\Currency;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('home');
});

Route::get('/get-rates', function () {
    // Call the getRates method from Currency model
    $currencyRates = Currency::getRates();

    // You can return the data as JSON or handle it as needed
    return $currencyRates;
});

