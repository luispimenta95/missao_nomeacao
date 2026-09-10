<?php

namespace App\Http\Controllers;

use App\Http\Util\MailHelper;
use App\Models\Inscricao;
use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $turma = Turma::findOrFail($data['turma_id']);

        if (! $turma->aceitaInscricao()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta preparação não está aceitando novos alunos no momento.'
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

        try {
            MailHelper::emailInscricao([
                'nome' => $data['name'],
                'tituloTurma' => $turma->nomePublicoExibido(),
                'url' => $turma->linkCta() ?: $turma->checkout_url,
            ], $data['email']);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de inscrição', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);
        }

        $redirectUrl = $turma->linkCta();

        if ($redirectUrl) {
            return response()->json([
                'success' => true,
                'redirect_url' => $redirectUrl,
                'message' => 'Inscrição realizada com sucesso! Redirecionando...'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscrição realizada com sucesso!'
        ]);
    }
}
