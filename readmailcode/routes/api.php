    <?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ApisControllers\GetmailController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api','client')->get('/user', function (Request $request) {
    return $request->all();
});

Route::get("/getemail",'ExternalController@getEmail');
Route::get("/check/message",'ExternalController@countEmail');


Route::get("/urgent/create",[GetmailController::class,'urgMailcreattion']);



Route::group(["prefix"=>"v1"],function(){
    
    Route::get("get-new-temporary-email",[GetmailController::class,'get_temp_mail']);
    Route::get("get-email-domains",[GetmailController::class,'get_email_domains']);
    Route::get("get-messages",[GetmailController::class,'get_messages']);
    Route::get("delete-message",[GetmailController::class,'del_message']);
    Route::post('login',[GetmailController::class,'login']);
    
    
    Route::get('get-authorization-token',[GetmailController::class,'get_authorization_token']);
    Route::post('payment-finalize',[GetmailController::class,'payment_finalize']);


    Route::group(["middleware"=>'auth:api'],function(){
        Route::post('get-my-emails',[GetmailController::class,'get_my_emails']);
        Route::post('create-custom-email',[GetmailController::class,'create_custom_email']);
        Route::post('logout',[GetmailController::class,'logout']);


        Route::get("cancel-subscription",[GetmailController::class,'cancel_subscription']);
        Route::get("get-profile",[GetmailController::class,'get_profile']);
        Route::post("change-password",[GetmailController::class,'change_password']);
    });


    Route::get("read-message",[GetmailController::class,'mark_as_read']);




    
    Route::get("forget-password",[GetmailController::class,'forget_password']);
});