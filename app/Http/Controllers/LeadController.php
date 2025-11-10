<?php

namespace App\Http\Controllers;

use App\Models\Lead;
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
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
        ]);

    // Normalize consent to boolean
    $data['consent'] = isset($data['consent']) ? true : false;


    Lead::create($data);

        // Download/redirect URL (the user asked to add utm_source=site to this URL)
        $downloadBase = 'https://pay.plataformatutory.com.br/checkout/19235f0f-222d-49a3-b9e0-f8cb71ee182a';
        $redirectUrl = $downloadBase . (strpos($downloadBase, '?') !== false ? '&' : '?') . http_build_query(['utm_source' => 'site']);

        return response()->json([
            'success' => true,
            'redirect_url' => $redirectUrl,
        ]);
    }
}
