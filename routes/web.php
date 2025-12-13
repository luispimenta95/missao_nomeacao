<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\TurmaController;
use App\Http\Controllers\InscricaoController;
use App\Http\Controllers\LeadAdminController;
use App\Http\Controllers\InscricaoAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\Material;
use App\Models\Turma;

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', function () {
    $materials = Material::with('turmas')->orderBy('created_at', 'desc')->get();
    $turmas = Turma::where('status', 'aberta')->orderBy('created_at', 'desc')->get();
    return view('landing', compact('materials', 'turmas'));
});

Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
Route::post('/inscricoes', [InscricaoController::class, 'store'])->name('inscricoes.store');

// Admin routes - protected by auth middleware
Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    // Material routes
    Route::get('/materiais', [MaterialController::class, 'index'])->name('materiais.index');
    Route::get('/materiais/create', [MaterialController::class, 'create'])->name('materiais.create');
    Route::post('/materiais', [MaterialController::class, 'store'])->name('materiais.store');
    Route::get('/materiais/{material}/edit', [MaterialController::class, 'edit'])->name('materiais.edit');
    Route::put('/materiais/{material}', [MaterialController::class, 'update'])->name('materiais.update');
    Route::delete('/materiais/{material}', [MaterialController::class, 'destroy'])->name('materiais.destroy');
    Route::get('/materiais/{material}/edit', [MaterialController::class, 'edit'])->name('materiais.edit');
    Route::put('/materiais/{material}', [MaterialController::class, 'update'])->name('materiais.update');
    Route::delete('/materiais/{material}', [MaterialController::class, 'destroy'])->name('materiais.destroy');

    // Turmas routes
    Route::get('/turmas', [TurmaController::class, 'index'])->name('turmas.index');
    Route::get('/turmas/create', [TurmaController::class, 'create'])->name('turmas.create');
    Route::post('/turmas', [TurmaController::class, 'store'])->name('turmas.store');
    Route::get('/turmas/{turma}/edit', [TurmaController::class, 'edit'])->name('turmas.edit');
    Route::put('/turmas/{turma}', [TurmaController::class, 'update'])->name('turmas.update');
    Route::delete('/turmas/{turma}', [TurmaController::class, 'destroy'])->name('turmas.destroy');

    // Leads routes
    Route::get('/leads', [LeadAdminController::class, 'index'])->name('leads.index');
    Route::delete('/leads/{lead}', [LeadAdminController::class, 'destroy'])->name('leads.destroy');
    Route::get('/leads/export/csv', [LeadAdminController::class, 'export'])->name('leads.export');

    // Inscricoes routes
    Route::get('/inscricoes', [InscricaoAdminController::class, 'index'])->name('inscricoes.index');
    Route::delete('/inscricoes/{inscricao}', [InscricaoAdminController::class, 'destroy'])->name('inscricoes.destroy');
    Route::get('/inscricoes/export/csv', [InscricaoAdminController::class, 'export'])->name('inscricoes.export');
});

// Public download route (forces download)
Route::get('/materiais/{material}/download', [MaterialController::class, 'download'])->name('materiais.download');
