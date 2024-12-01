<?php

use App\Http\Controllers\userAuthController;
use App\Http\Controllers\CategoryListController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('signup', [userAuthController::class,'signup']);
Route::post('login', [userAuthController::class,'login']);
Route::get('categories/{type}/{user_id}', [CategoryListController::class,'index']);
Route::post('categories/add', [CategoryListController::class,'individualCategoryAdd']);
Route::post('categories/delete/{category_id}/{user_id}', [CategoryListController::class,'destroy']);

Route::get('transactions/{user_id}', [TransactionController::class,'index']);
Route::post('transactions/add', [TransactionController::class,'create']);

Route::get('dashboard/{user_id}', [DashboardController::class,'index']);

