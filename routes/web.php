<?php

use App\Http\Controllers\ClientAreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'auth.session', 'log.route.visit', 'inactivity.protected', 'ensure.mfa'])->group(function () {
    Route::get('/home', function (Request $request): RedirectResponse {
        return redirect()->route($request->user()->homeRouteName());
    })->name('home.redirect');

    Route::get('/cliente', ClientAreaController::class)
        ->middleware('role:cliente')
        ->name('client.home');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('role:usuario,administrador,logger')
        ->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->middleware('role:cliente')
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('role:cliente')
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('role:cliente')
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';
