<?php

use App\Http\Controllers\IngestionOpsController;
use App\Http\Controllers\InstitutionBrowseController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/institutions', InstitutionBrowseController::class)->name('institutions.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('internal/ingestion')->name('internal.ingestion.')->group(function (): void {
        Route::post('/all-england/run', [IngestionOpsController::class, 'runAllEngland'])->name('all-england.run');
        Route::get('/reports', [IngestionOpsController::class, 'reports'])->name('reports');
    });
});

require __DIR__.'/auth.php';
