<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\AffectationController;
use App\Http\Controllers\PlanningController;



Route::get('/', [ImportController::class, 'index'])
    ->name('imports.index');
Route::post('/', [ImportController::class, 'importAll'])
    ->name('imports.all');


Route::get('/affectations', [AffectationController::class, 'index'])
    ->name('affectations.index');
    
Route::post('/affectations/generate', [AffectationController::class, 'generate'])
    ->name('affectations.generate');

    
Route::get('/planning', [PlanningController::class, 'index'])
    ->name('planning.index');

Route::post('/planning/generate', [PlanningController::class, 'generate'])
    ->name('planning.generate');
