<?php

use App\Http\Controllers\admin\LoginController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\CustomerController;
use App\Http\Controllers\admin\TransactionController;
use App\Http\Controllers\admin\MillageController;
use App\Http\Controllers\admin\EmailTemplateController;
use App\Http\Controllers\admin\CategoryListController;
use App\Http\Controllers\admin\EditCategoryController;
use App\Http\Controllers\admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

Route::group(['prefix' => 'admin'],function(){
    Route::group(['middleware'=>'admin.guest'],function(){
        Route::get('login', [LoginController::class, 'index'])->name('admin.login');
        Route::post('authenticate', [LoginController::class, 'authenticate'])->name('admin.authenticate');

    });
    Route::group(['middleware'=>'admin.auth'],function(){
        Route::get('logout', [LoginController::class, 'logout'])->name('admin.logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('users', [CustomerController::class, 'index'])->name('admin.customer');
        Route::get('user/upcomingsubscription', [CustomerController::class, 'upcomingsubscription'])->name('admin.upcomingsubscription');
        Route::post('user/suspend', [CustomerController::class, 'suspend'])->name('admin.suspendcustomer');
        Route::get('users/{userid}', [CustomerController::class, 'edituser'])->name('admin.editcustomer');
        
        Route::post('searchusers', [CustomerController::class, 'index'])->name('admin.searchcustomer');
        Route::get('transactions', [TransactionController::class, 'index'])->name('admin.transactions');
        Route::get('transactions/{transactionid}', [TransactionController::class, 'getTransaction'])->name('admin.transactionsidwise');
        //Route::post('transactions', [TransactionController::class, 'index'])->name('admin.searchtransactions');
        Route::get('millages', [MillageController::class, 'index'])->name('admin.millage');
        Route::get('millages/{millageId}', [MillageController::class, 'getMillage'])->name('admin.millageidwise');
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('admin.subscriptions');
        //Route::get('millages/{userid}', [MillageController::class, 'index'])->name('admin.millageuserwise');
        //Route::post('millages', [MillageController::class, 'index'])->name('admin.searchmillage');
        Route::get('emailtemplates', [EmailTemplateController::class, 'index'])->name('admin.emailtemplate');
        Route::post('emailtemplates', [EmailTemplateController::class, 'index'])->name('admin.searchemailtemplate');
        Route::get('emailtemplates/{templateId}', [EmailTemplateController::class, 'getTemplate'])->name('admin.getemailtemplate');
        Route::post('emailtemplates/edit', [EmailTemplateController::class, 'edit'])->name('admin.editemailtemplate');
        Route::get('categories', [CategoryListController::class, 'index'])->name('admin.categories');
        Route::get('category/add', [CategoryListController::class, 'addcategory'])->name('admin.addcategory');
        Route::post('category/add', [CategoryListController::class, 'individualCategoryAdd'])->name('admin.postaddcategories');
        Route::get('categories/{catid}', [EditCategoryController::class, 'getcategory'])->name('admin.editcategories');
        Route::post('categories/{catid}', [EditCategoryController::class, 'updatecategories'])->name('admin.updatedategories');
    });
});


require __DIR__.'/auth.php';
