<?php

namespace App\Http\Controllers;

use App\Models\Inscricao;
use App\Models\Turma;
use Illuminate\Http\Request;

class InscricaoController extends Controller
{
    /**
     * Store a newly created inscricao in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'turma_id' => 'required|exists:turmas,id',
        ]);

        // Get turma
        $turma = Turma::findOrFail($data['turma_id']);

        // Check if there are available slots
        if ($turma->available_slots !== null && $turma->available_slots <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Não há mais vagas disponíveis para esta turma.'
            ], 400);
        }

        // Create inscricao
        Inscricao::create($data);

        // Decrease available slots if they exist
        if ($turma->available_slots !== null) {
            $turma->decrement('available_slots');
            
            // Check if turma is now full
            if ($turma->fresh()->available_slots <= 0) {
                $turma->update(['status' => 'completa']);
            }
        }

        // Redirect to checkout URL
        if ($turma->checkout_url) {
            return response()->json([
                'success' => true,
                'redirect_url' => $turma->checkout_url,
                'message' => 'Inscrição realizada com sucesso! Redirecionando para o checkout...'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscrição realizada com sucesso!'
        ]);
    }
}
