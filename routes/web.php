<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\UserCredits\CreditsController;
use App\Http\Controllers\Squid\PortsController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    'verified',
])->prefix('dashboard')->name('dashboard.')
    ->group(function () {

        Route::prefix('credits')->name('credits.')->group(function () {
            Route::get('/', [CreditsController::class, 'index'])->name('index');
            
            Route::post('/', [CreditsController::class, 'create'])->name('create');
        });

        Route::prefix('ports')->name('ports.')->group(function () {

            Route::get('/', [PortsController::class, 'index'])->name('index');

            Route::post('/{port}/test', [PortsController::class, 'testProxy'])->name('test');

            Route::patch('/{port}/toggle-renovation', [PortsController::class, 'toggleRenovation'])->name('toggle-renovation');
        });
    });
