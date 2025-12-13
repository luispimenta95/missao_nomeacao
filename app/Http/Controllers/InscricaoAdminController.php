<?php

namespace App\Http\Controllers;

use App\Models\Inscricao;
use App\Models\Turma;
use Illuminate\Http\Request;
use Carbon\Carbon;

class InscricaoAdminController extends Controller
{
    /**
     * Display a listing of inscricoes.
     */
    public function index(Request $request)
    {
        $selectedDate = $request->query('date');
        
        $query = Inscricao::with('turma');
        
        if ($selectedDate) {
            $query->whereYear('created_at', '=', date('Y', strtotime($selectedDate . '-01')))
                  ->whereMonth('created_at', '=', date('m', strtotime($selectedDate . '-01')));
        }
        
        $inscricoes = $query->orderBy('created_at', 'desc')->paginate(15);
        
        $totalInscricoes = Inscricao::count();
        $inscricoesThisMonth = Inscricao::whereMonth('created_at', Carbon::now()->month)
                                        ->whereYear('created_at', Carbon::now()->year)
                                        ->count();
        $activeturmas = Turma::where('status', 'aberta')->count();
        
        // Encontrar turma com mais inscritos
        $turmaComMaisInscritos = Turma::withCount('inscricoes')
            ->orderByDesc('inscricoes_count')->whereMonth('created_at', Carbon::now()->month)
                                        ->whereYear('created_at', Carbon::now()->year)
            ->first();

        return view('admin.inscricoes.index', compact('inscricoes', 'totalInscricoes', 'inscricoesThisMonth', 'activeturmas', 'turmaComMaisInscritos', 'selectedDate'));
    }

    /**
     * Delete an inscricao.
     */
    public function destroy(Inscricao $inscricao)
    {
        // Restore available slots if it was decreased
        if ($inscricao->turma && $inscricao->turma->available_slots !== null) {
            $inscricao->turma->increment('available_slots');
            
            // Check if turma should revert to open status
            if ($inscricao->turma->status === 'completa' && $inscricao->turma->fresh()->available_slots > 0) {
                $inscricao->turma->update(['status' => 'aberta']);
            }
        }
        
        $inscricao->delete();
        return redirect()->route('inscricoes.index')->with('success', 'Inscrição deletada com sucesso.');
    }

    /**
     * Export inscricoes to CSV.
     */
    public function export()
    {
        $inscricoes = Inscricao::with('turma')->orderBy('created_at', 'desc')->get();

        $csv = "Nome,Email,Telefone,Turma,Data da Inscrição\n";
        
        foreach ($inscricoes as $inscricao) {
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $inscricao->name) . '"',
                $inscricao->email,
                $inscricao->phone,
                '"' . str_replace('"', '""', $inscricao->turma->title) . '"',
                $inscricao->created_at->format('d/m/Y H:i')
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=inscricoes_' . now()->format('Y-m-d_His') . '.csv',
        ]);
    }
}
