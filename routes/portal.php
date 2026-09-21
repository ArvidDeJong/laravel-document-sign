<?php

use Darvis\Signer\Http\Controllers\Portal\AuthController;
use Darvis\Signer\Http\Controllers\Portal\ContactController;
use Darvis\Signer\Http\Controllers\Portal\CustomerController;
use Darvis\Signer\Http\Controllers\Portal\DashboardController;
use Darvis\Signer\Http\Controllers\Portal\DocumentController;
use Darvis\Signer\Http\Middleware\AuthenticatePortal;
use Darvis\Signer\SignerServiceProvider;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Route;

$config = app(SignerConfig::class);

Route::prefix($config->portalPrefix())
    ->middleware($config->portalMiddleware())
    ->name('signer.portal.')
    ->group(function () {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])
            ->middleware('throttle:'.SignerServiceProvider::LOGIN_RATE_LIMITER)
            ->name('login.store');

        Route::middleware(AuthenticatePortal::class)->group(function () {
            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

            Route::get('/', DashboardController::class)->name('dashboard');

            Route::resource('customers', CustomerController::class)
                ->except(['show'])
                ->parameters(['customers' => 'customer']);

            Route::post('customers/{customer}/contacts', [ContactController::class, 'store'])
                ->name('customers.contacts.store');
            Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])
                ->name('contacts.destroy');

            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('documents/create', [DocumentController::class, 'create'])->name('documents.create');
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::get('documents/{document}/download/{type}', [DocumentController::class, 'download'])
                ->whereIn('type', ['original', 'signed'])
                ->name('documents.download');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });
    });
