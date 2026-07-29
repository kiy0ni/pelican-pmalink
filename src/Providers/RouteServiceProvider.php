<?php

namespace KiyOni\PmaLink\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use KiyOni\PmaLink\Controllers\PmaController;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware(['web', 'auth'])
                ->prefix('/pmalink')
                ->scopeBindings()
                ->group(function () {
                    Route::get('/redirect/{database}', [PmaController::class, 'redirect'])
                        ->name('pmalink.redirect');
                });

            Route::middleware(['throttle:60,1'])
                ->prefix('/pmalink')
                ->group(function () {
                    Route::get('/verify/{token}', [PmaController::class, 'verify'])
                        ->name('pmalink.verify');
                });
        });
    }
}
