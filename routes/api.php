<?php

use App\Http\Controllers\userAuthController;
use App\Http\Controllers\CategoryListController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\MillageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TaxCalculationController;
use App\Http\Controllers\WwfhController;
use App\Http\Controllers\DepreciationController;
use App\Http\Controllers\WfhController;
use App\Http\Controllers\AIChatController;
use App\Http\Controllers\HmrcClientAuthorisationController;
use App\Http\Controllers\HmrcMtdController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Kreait\Firebase\Factory;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('signup', [userAuthController::class,'signup']);
Route::post('storeuser', [userAuthController::class,'storeUser']);
Route::post('contactus', [userAuthController::class,'contactus']);
Route::post('forgetpassword', [userAuthController::class,'resetpasswordrequest']);
Route::post('resendotp', [userAuthController::class,'resendotp']);
Route::post('login', [userAuthController::class,'login']);
Route::post('user/verifyemail', [userAuthController::class,'verifyemail']);
Route::post('subscription/createsubscription', [SubscriptionController::class,'createSubscription']);
Route::post('subscription/createstripesubscription', [SubscriptionController::class,'createStripeSubscription']);
Route::post('subscription/webhook', [SubscriptionController::class,'getpaymentInfo']);
Route::post('send-push-notification', [NotificationController::class, 'sendPushNotification']);
Route::get('send-version-notification', [NotificationController::class, 'sendversionnotification']);
Route::get('send-version-ios-notification', [NotificationController::class, 'sendiosversionnotification']);
Route::post('updatepassword', [userAuthController::class,'updateforgotpassword']);
Route::get('subscription/stripekeys', [SubscriptionController::class,'stripeAuth']);
Route::get('subscription/trialendday', [SubscriptionController::class,'getTrialEndDate']);


Route::middleware(['auth:sanctum','check.user.status'])->group(function(){
    Route::post('user/updateprofile', [userAuthController::class,'updateprofile']);
    Route::post('user/updatemobile', [userAuthController::class,'updatemobile']);
    Route::post('user/updateavatar', [userAuthController::class,'updateavatar']);
    Route::post('user/updatepassword', [userAuthController::class,'updatepassword']);
    Route::post('user/updateemailverified', [userAuthController::class,'updateemailverified']);
    Route::post('user/updatefcmtoken', [userAuthController::class,'updatefcmtoken']);
    Route::post('user/deleteuser', [userAuthController::class,'deleteUser']);
    Route::get('categories/{type}/{user_id}', [CategoryListController::class,'index']);
    Route::get('categories/{type}', [CategoryListController::class,'index']);
    Route::post('categories/add', [CategoryListController::class,'individualCategoryAdd']);
    Route::post('categories/edit', [CategoryListController::class,'edit']);
    Route::post('categories/delete/{category_id}/{user_id}', [CategoryListController::class,'destroy']);
    Route::get('user/getprofile/{user_id}', [userAuthController::class,'getprofile']);
    Route::post('user/getreferred', [userAuthController::class,'getReferred']);
    Route::get('dashboard/{user_id}', [DashboardController::class,'index']);
    Route::post('transactions', [TransactionController::class,'index']);
    Route::get('transactions/profit/{user_id}/{duration}', [TransactionController::class,'datacurrentmonth']);
    Route::post('transactions/add', [TransactionController::class,'create']);
    Route::post('transactions/edit', [TransactionController::class,'edit']);
    Route::post('transactions/removerecurring', [TransactionController::class,'removerecurring']);
    Route::post('transactions/delete/image', [TransactionController::class,'removeTransactionImage']);
    Route::post('transactions/delete', [TransactionController::class,'destroy']);
    Route::post('transactions/depreciationcalculator', [TransactionController::class,'depreciationCalculator']);
    Route::post('millage/add', [MillageController::class,'create']);
    Route::post('millage/edit', [MillageController::class,'edit']);
    Route::post('millage/delete/image', [MillageController::class,'removeMillageImage']);
    Route::post('millage/delete', [MillageController::class,'destroy']);
    Route::post('millage', [MillageController::class,'index']);
    Route::get('dashboard/{date}/{user_id}', [DashboardController::class,'getTransactions']);
    Route::get('calendar/{user_id}', [DashboardController::class,'calendar']);
    Route::get('subscription/{user_id}', [SubscriptionController::class,'getList']);
    Route::get('subscription/revenuecat/{user_id}', [SubscriptionController::class,'getRevenueCatList']);
    Route::post('subscription/create-payment-intent', [SubscriptionController::class,'createPaymentIntent']);


    Route::post('subscription/removesubscription', [SubscriptionController::class,'removeSubscription']);
    Route::post('subscription/removestripesubscription', [SubscriptionController::class,'removeStripeSubscription']);
    Route::post('subscription/renewsuscription', [SubscriptionController::class,'renewsuscription']);
    Route::post('subscription/renewsuscriptionemail', [SubscriptionController::class,'renewsuscriptionemail']);
    Route::post('reminder/setreminder', [ReminderController::class, 'setReminder']);
    Route::get('reminder/getreminder/{user_id}', [ReminderController::class, 'getReminder']);
    Route::post('reminder/edit', [ReminderController::class,'updateReminder']);
    Route::post('reminder/delete', [ReminderController::class,'destroy']);
    Route::get('tax/calculate/weekly/{calculation_type}/{year}/{user_id}', [TaxCalculationController::class,'taxweekly']);
    Route::get('tax/calculate/yearly/{calculation_type}/{year}/{user_id}', [TaxCalculationController::class,'taxyeartodate']);
    Route::get('tax/getfinancialyears', [TaxCalculationController::class,'getLastThreeFinancialYears']);
    Route::get('notifications/{user_id}', [NotificationController::class,'getNotifications']);
    Route::post('notifications/edit', [NotificationController::class,'updateNotification']);
    Route::post('notifications/delete', [NotificationController::class,'removeNotification']);
    Route::post('dashboard/getreport', [DashboardController::class,'getReport']);
    Route::get('monthlyreport/{user_id}/{month}/{year}', [DashboardController::class,'monthlyreport']);
    Route::get('monthlyreport/months/{year}', [DashboardController::class,'monthlists']);
    Route::post('wfh/add', [WfhController::class,'setData']);
    Route::get('wfh/get/{user_id}', [WfhController::class,'getData']);
    Route::get('wfh/iswfhalreadyset/{user_id}', [WfhController::class,'iswfhalreadyset']);
    Route::post('depreciation/setdepreciation', [DepreciationController::class,'setData']);
    Route::post('depreciation/getdepreciation', [DepreciationController::class,'getData']);
    Route::get('depreciation/iswdaalreadyset/{user_id}', [DepreciationController::class,'iswdaalreadyset']);
    Route::get('subscription/trialendday/{user_id}', [SubscriptionController::class,'getuserTrialEndDate']);

    Route::post('openai/conversation', [AIChatController::class, 'createConversation']);
    Route::get('openai/conversations/{user_id}', [AIChatController::class, 'conversations']);
    Route::get('openai/conversation/{conversation}/{user_id}', [AIChatController::class, 'history']);
    Route::post('openai/send', [AIChatController::class, 'send']);
    Route::delete('openai/conversation/{conversation}/{user_id}', [AIChatController::class, 'destroy']);

    Route::post('hmrc/client-authorisation', [HmrcClientAuthorisationController::class, 'store']);
    Route::put('hmrc/client-authorisation/{invitationId}/accept-sandbox', [HmrcClientAuthorisationController::class, 'acceptSandbox']);
    Route::get('hmrc/client-authorisation/status', [HmrcClientAuthorisationController::class, 'status']);

    Route::post('hmrc/relationship/check',[HmrcMtdController::class, 'checkRelationship']);
    Route::post('hmrc/savebusinesses',[HmrcMtdController::class, 'savebusinesses']);
    Route::get('hmrc/mtdstatus',[HmrcMtdController::class, 'mtdStatus']);
    Route::get('hmrc/businesses',[HmrcMtdController::class, 'businesses']);
    Route::get('hmrc/dashboard',[HmrcMtdController::class, 'dashboard']);
    Route::get('hmrc/obligations',[HmrcMtdController::class, 'obligations']);
    Route::put('hmrc/quarterly-update',[HmrcMtdController::class,'submitQuarterlyUpdate']);
    Route::put('hmrc/annual-submission',[HmrcMtdController::class,'submitAnnualSubmission']);
    Route::post('hmrc/trigger-calculation',[HmrcMtdController::class,'triggerCalculation']);
    Route::get('hmrc/retrieve-calculation/{calculationId}',[HmrcMtdController::class,'retrieveCalculation']);
    Route::get('/hmrc/mtd/account-summary',[HmrcMtdController::class, 'accountSummary']);
    
    Route::get('/hmrc/mtd/payments-and-allocations',[HmrcMtdController::class, 'paymentsAndAllocations']);
    Route::post('hmrc/trigger-final-calculation',[HmrcMtdController::class,'triggerFinalCalculation']);
    Route::post('hmrc/submit-final-declaration',[HmrcMtdController::class,'submitFinalDeclaration']);

});

