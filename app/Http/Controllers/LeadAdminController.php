<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeadAdminController extends Controller
{
    /**
     * Display a listing of leads.
     */
    public function index()
    {
        $leads = Lead::with('material')->orderBy('created_at', 'desc')->paginate(15);
        
        $totalLeads = Lead::count();
        $leadsWithConsent = Lead::where('consent', true)->count();
        $leadsThisMonth = Lead::whereMonth('created_at', Carbon::now()->month)
                              ->whereYear('created_at', Carbon::now()->year)
                              ->count();

        return view('admin.leads.index', compact('leads', 'totalLeads', 'leadsWithConsent', 'leadsThisMonth'));
    }

    /**
     * Delete a lead.
     */
    public function destroy(Lead $lead)
    {
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead deletado com sucesso.');
    }

    /**
     * Export leads to CSV.
     */
    public function export()
    {
        $leads = Lead::with('material')->orderBy('created_at', 'desc')->get();

        $csv = "Nome,Email,Telefone,Material,Consentimento,Data\n";
        
        foreach ($leads as $lead) {
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $lead->name) . '"',
                $lead->email,
                $lead->phone ?? '',
                '"' . str_replace('"', '""', $lead->material?->title ?? '') . '"',
                $lead->consent ? 'Sim' : 'Não',
                $lead->created_at->format('d/m/Y H:i')
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=leads_' . now()->format('Y-m-d_His') . '.csv',
        ]);
    }
}
