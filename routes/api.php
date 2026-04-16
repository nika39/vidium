<?php

use App\Http\Controllers\Api\MetricController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('metrics', [MetricController::class, 'store'])->name('metrics.store');
