<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApisControllers\GetmailController;

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
    return 'hello world it sme';
    return view('welcome');
});

Route::get('password/reset/{token}',[GetmailController::class,'reset_pwd'])->name('password.reset');

Route::get('/payment/process', [GetmailController::class,'process'])->name('payment.process');