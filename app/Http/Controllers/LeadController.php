<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Material;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Store a newly created lead in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'consent' => 'accepted',
            'material_id' => 'nullable|exists:materials,id',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
        ]);

    // Normalize consent to boolean
    $data['consent'] = isset($data['consent']) ? true : false;
        // Force utm_source to 'site' (fixed value)
        $data['utm_source'] = 'site';
        // Accept either 'material_id' or legacy 'file' param (slug)
        $materialId = $request->input('material_id') ?: null;

        // Persist lead with selected material if present
        if ($materialId) {
            $data['material_id'] = $materialId;
        }

        Lead::create($data);

        if ($materialId) {
            // Redirect to the material's external link (Tutory) when available
            $material = Material::find($materialId);
            if ($material && !empty($material->link)) {
                $redirectUrl = $material->link;
            } else {
                // Fallback to internal download route if link not found
                $redirectUrl = route('materiais.download', ['material' => $materialId]);
            }
        } else {
            // Fallback: original pay URL with utm_source=site
            $downloadBase = 'https://pay.plataformatutory.com.br/checkout/19235f0f-222d-49a3-b9e0-f8cb71ee182a';
            $redirectUrl = $downloadBase . (strpos($downloadBase, '?') !== false ? '&' : '?') . http_build_query(['utm_source' => 'site']);
        }

        return response()->json([
            'success' => true,
            'redirect_url' => $redirectUrl,
        ]);
    }
}
