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
        'utm_medium' => 'nullable|string|max:255',
        'utm_campaign' => 'nullable|string|max:255',
    ]);

    $data['consent'] = true;
    $data['utm_source'] = 'site';

    $materialId = $request->input('material_id');
    if ($materialId) {
        $data['material_id'] = $materialId;
    }

    try {
        Lead::create($data);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erro ao salvar os dados.'
        ], 400);
    }

    // URLs
    if ($materialId) {
        $material = Material::find($materialId);

        $downloadUrl = $material && $material->link
            ? $material->link
            : route('materiais.download', ['material' => $materialId]);

        $redirectUrl = $downloadUrl;
    } else {
        $downloadUrl = null;
        $redirectUrl = 'https://pay.plataformatutory.com.br/checkout/19235f0f-222d-49a3-b9e0-f8cb71ee182a?utm_source=site';
    }

    // 👉 SE FOR AJAX
    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'download_url' => $downloadUrl,
            'redirect_url' => $redirectUrl,
        ]);
    }

    // 👉 SE NÃO FOR AJAX (HTML + JS)
    return response()->make("
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Processando...</title>
        </head>
        <body>
            <p>Seu download está sendo iniciado...</p>

            <script>
                (function () {
                    " . ($downloadUrl ? "
                        const a = document.createElement('a');
                        a.href = '{$downloadUrl}';
                        a.download = '';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    " : "") . "

                    setTimeout(function () {
                        window.location.href = '{$redirectUrl}';
                    }, 1500);
                })();
            </script>
        </body>
        </html>
    ");
}

}
