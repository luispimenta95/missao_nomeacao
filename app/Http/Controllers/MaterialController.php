<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MaterialController extends Controller
{
    public function index()
    {
        $materials = Material::with('turmas')->orderBy('created_at', 'desc')->get();
        return view('admin.materiais.index', compact('materials'));
    }

    public function create()
    {
        $turmas = \App\Models\Turma::orderBy('title')->get();
        return view('admin.materiais.create', compact('turmas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf|max:20480',
            'link' => 'nullable|url|max:255',
            'turmas' => 'required|array|min:1',
            'turmas.*' => 'exists:turmas,id',
        ], [
            'turmas.required' => 'Você deve selecionar pelo menos uma turma.',
            'turmas.min' => 'Você deve selecionar pelo menos uma turma.',
        ]);

        $file = $request->file('file');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time() . '.pdf';

        // Store in storage/app/public/materials
        $path = $file->storeAs('materials', $filename, 'public');

        $material = Material::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'link' => $data['link'] ?? null,
        ]);

        // Attach turmas
        if (!empty($data['turmas'])) {
            $material->turmas()->attach($data['turmas']);
        }

        return redirect()->route('materiais.index')->with('success', 'Material salvo com sucesso.');
    }

    public function download(Material $material)
    {
        // Get the storage disk and check if file exists
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        
        if (!$disk->exists($material->file_path)) {
            \Log::error('PDF file not found: ' . $material->file_path);
            abort(404, 'Arquivo não encontrado.');
        }

        // Get full path to file
        $filePath = $disk->path($material->file_path);
        $filename = pathinfo($material->file_path, PATHINFO_BASENAME);

        // Return download response with proper headers
        return response()->download(
            $filePath,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function edit(Material $material)
    {
        $turmas = \App\Models\Turma::orderBy('title')->get();
        return view('admin.materiais.edit', compact('material', 'turmas'));
    }

    public function update(Request $request, Material $material)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'link' => 'nullable|url|max:255',
            'turmas' => 'required|array|min:1',
            'turmas.*' => 'exists:turmas,id',
        ], [
            'turmas.required' => 'Você deve selecionar pelo menos uma turma.',
            'turmas.min' => 'Você deve selecionar pelo menos uma turma.',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time() . '.pdf';
            $path = $file->storeAs('materials', $filename, 'public');
            $data['file_path'] = $path;
        }

        unset($data['file']);
        unset($data['turmas']);
        $material->update($data);

        // Sync turmas
        if (isset($request->turmas)) {
            $material->turmas()->sync($request->turmas);
        } else {
            $material->turmas()->sync([]);
        }

        return redirect()->route('materiais.index')->with('success', 'Material atualizado com sucesso.');
    }

    public function destroy(Material $material)
    {
        // Delete the file if it exists
        if ($material->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($material->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return redirect()->route('materiais.index')->with('success', 'Material deletado com sucesso.');
    }
}
