<?php

use Darvis\Signer\Http\Controllers\SignController;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Route;

$config = app(SignerConfig::class);

Route::prefix($config->routePrefix())
    ->middleware($config->routeMiddleware())
    ->group(function () {
        Route::get('{signer:uuid}', [SignController::class, 'show'])
            ->middleware('signed')
            ->name('signer.show');

        Route::get('{signer:uuid}/pdf', [SignController::class, 'pdf'])
            ->name('signer.pdf');

        Route::post('{signer:uuid}', [SignController::class, 'store'])
            ->name('signer.store');

        Route::get('{signer:uuid}/done', [SignController::class, 'done'])
            ->name('signer.done');
    });
