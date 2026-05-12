<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;

Route::get('/imports', [ImportController::class, 'index'])
    ->name('imports.index');

Route::post('/imports', [ImportController::class, 'importAll'])
    ->name('imports.all');

