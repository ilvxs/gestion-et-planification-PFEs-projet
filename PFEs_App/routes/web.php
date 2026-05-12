<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;

Route::get('/', [ImportController::class, 'index'])
    ->name('imports.index');

Route::post('/', [ImportController::class, 'importAll'])
    ->name('imports.all');

