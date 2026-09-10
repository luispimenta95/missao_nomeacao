<?php

use App\Http\Controllers\Api\TurmaPublicController;
use Illuminate\Support\Facades\Route;

Route::get('/turmas', [TurmaPublicController::class, 'index']);
Route::get('/turmas/{slug}', [TurmaPublicController::class, 'show']);
