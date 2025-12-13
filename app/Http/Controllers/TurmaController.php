<?php

namespace App\Http\Controllers;

use App\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TurmaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $turmas = Turma::orderBy('status', 'asc')->orderBy('created_at', 'desc')->get();
        return view('admin.turmas.index', compact('turmas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.turmas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:5120',
            'checkout_url' => 'required|url|max:500',
            'start_date' => 'nullable|date',
            'available_slots' => 'nullable|integer|min:1',
            'status' => 'required|in:aberta,fechada,completa',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = Str::slug($data['title']) . '-' . time() . '.' . $file->getClientOriginalExtension();
            $logoPath = $file->storeAs('turmas', $filename, 'public');
            $data['logo_path'] = $logoPath;
        }

        unset($data['logo']);
        Turma::create($data);

        return redirect()->route('turmas.index')->with('success', 'Turma criada com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Turma $turma)
    {
        return view('admin.turmas.show', compact('turma'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Turma $turma)
    {
        return view('admin.turmas.edit', compact('turma'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Turma $turma)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:5120',
            'checkout_url' => 'required|url|max:500',
            'start_date' => 'nullable|date',
            'available_slots' => 'nullable|integer|min:1',
            'status' => 'required|in:aberta,fechada,completa',
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = Str::slug($data['title']) . '-' . time() . '.' . $file->getClientOriginalExtension();
            $logoPath = $file->storeAs('turmas', $filename, 'public');
            $data['logo_path'] = $logoPath;
        }

        unset($data['logo']);
        $turma->update($data);

        return redirect()->route('turmas.index')->with('success', 'Turma atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Turma $turma)
    {
        $turma->delete();
        return redirect()->route('turmas.index')->with('success', 'Turma deletada com sucesso.');
    }
}
