<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MaterialController extends Controller
{
    public function index()
    {
        $materials = Material::orderBy('created_at', 'desc')->get();
        return view('admin.materials.index', compact('materials'));
    }

    public function create()
    {
        return view('admin.materials.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf|max:20480',
            'link' => 'required|url|max:255',
        ]);

        $file = $request->file('file');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time() . '.pdf';

        // Store in storage/app/public/materials
        $path = $file->storeAs('materials', $filename, 'public');

        $material = Material::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'link' => $data['link'],
        ]);

        return redirect()->route('materiais.index')->with('success', 'Material salvo com sucesso.');
    }

    public function download(Material $material)
    {
        // File is stored in storage/app/public/{file_path}
        $storagePath = storage_path('app/public/' . $material->file_path);
        if (!file_exists($storagePath)) {
            abort(404);
        }

        return response()->download($storagePath, basename($material->file_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
