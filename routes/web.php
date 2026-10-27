<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\SummaryController;
use App\Http\Middleware\RequireRole;
use Illuminate\Support\Facades\Route;

// Public: login and registration only (logged-out visitors)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

// Everything else needs a login
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::view('/', 'index');
    Route::get('/home', [BranchController::class, 'home']);
    Route::get('/branch/{id}', [BranchController::class, 'show'])->whereNumber('id');
    Route::post('/add-production', [BranchController::class, 'addProduction']);
    Route::post('/add-inventory', [BranchController::class, 'addInventory']);
    Route::post('/use-inventory', [BranchController::class, 'useInventory']);

    // Owner only
    Route::get('/summary', [SummaryController::class, 'index'])
        ->middleware(RequireRole::class . ':owner');
});