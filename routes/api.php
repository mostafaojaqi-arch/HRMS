<?php

use App\Http\Controllers\Api\TimeSheetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('timesheet')->group(function () {
    Route::get('/daily', [TimeSheetController::class, 'daily']);
    Route::get('/monthly', [TimeSheetController::class, 'monthly']);
    Route::get('/link-status', [TimeSheetController::class, 'linkStatus']);
});
