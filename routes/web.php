<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MaterialController;
use App\Models\Material;

Route::get('/', function () {
    $materials = Material::orderBy('created_at', 'desc')->get();
    return view('landing', compact('materials'));
});

Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');

// Admin material routes (names in Portuguese to match views/controllers)
Route::get('/admin/materiais', [MaterialController::class, 'index'])->name('materiais.index');
Route::get('/admin/materiais/create', [MaterialController::class, 'create'])->name('materiais.create');
Route::post('/admin/materiais', [MaterialController::class, 'store'])->name('materiais.store');

// Public download route (forces download)
Route::get('/materiais/{material}/download', [MaterialController::class, 'download'])->name('materiais.download');
