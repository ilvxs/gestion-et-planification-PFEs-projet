<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\AffectationController;


Route::get('/', [ImportController::class, 'index'])
    ->name('imports.index');
Route::post('/', [ImportController::class, 'importAll'])
    ->name('imports.all');


Route::get('/affectations', [AffectationController::class, 'index'])
    ->name('affectations.index');
Route::post('/affectations/generate', [AffectationController::class, 'generate'])
    ->name('affectations.generate');

