<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\UserCredits\CreditsController;
use App\Http\Controllers\Squid\PortsController;

use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard.ports.index');
    }

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
            Route::post('/{port}/rotate', [PortsController::class, 'rotateIp'])->name('rotate');
            Route::post('/ports/rotate-all', [PortsController::class, 'rotateAllIps'])->name('rotate-all');

            Route::patch('/toggle-renovation', [PortsController::class, 'toggleRenovation'])->name('toggle-renovation');
            Route::post('/renew-all', [PortsController::class, 'renewAllPorts'])->name('renew-all');
        });
    });
