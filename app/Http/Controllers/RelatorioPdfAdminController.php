<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Services\Tutory\PdfFontes;
use App\Services\Tutory\PdfPreview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RelatorioPdfAdminController extends Controller
{
    public function index()
    {
        return view('admin.relatorios-pdf.index', [
            'fonteAtual' => PdfFontes::chaveAtual(),
            'opcoes' => PdfFontes::opcoes(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'fonte' => ['required', 'string', Rule::in(array_keys(PdfFontes::opcoes()))],
        ]);

        Configuracao::definir(PdfFontes::CONFIG_CHAVE, $data['fonte']);

        return redirect()
            ->route('relatorios-pdf.index')
            ->with('success', 'Fonte do PDF atualizada. O preview abaixo já usa a nova escolha.');
    }

    public function preview(Request $request, PdfPreview $preview)
    {
        $query = $request->query('fonte');
        $fonte = is_string($query) && $query !== ''
            ? PdfFontes::normalizar($query)
            : PdfFontes::chaveAtual();
        $bytes = $preview->gerar($fonte);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-relatorio-'.$fonte.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
