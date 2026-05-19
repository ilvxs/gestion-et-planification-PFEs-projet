<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\AffectationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\DocumentController;

Route::get('/', [ImportController::class, 'index'])
    ->name('imports.index');

Route::post('/', [ImportController::class, 'importAll'])
    ->name('imports.all');

Route::post('/affectations/generate', [AffectationController::class, 'generate'])
    ->name('affectations.generate');

Route::post('/planning/generate', [PlanningController::class, 'generate'])
    ->name('planning.generate');

Route::get('/verification', [VerificationController::class, 'index'])
    ->name('verification.index');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard.index');

Route::get('/exportation', [ExportController::class, 'index'])
    ->name('export.index');

Route::get('/documents/planning', [DocumentController::class, 'planning'])
    ->name('documents.planning');

Route::get('/documents/affectations', [DocumentController::class, 'affectation'])
    ->name('documents.affectations');

Route::get('/documents/pvs', [DocumentController::class, 'pvs'])
    ->name('documents.pvs');
