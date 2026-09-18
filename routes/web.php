<?php

use Darvis\Signer\Http\Controllers\SignController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('signer.route_prefix'))
    ->middleware(config('signer.route_middleware'))
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
