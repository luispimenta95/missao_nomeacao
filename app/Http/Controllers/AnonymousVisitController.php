<?php

namespace App\Http\Controllers;

use App\Models\AnonymousVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AnonymousVisitController extends Controller
{
    /**
     * Store the entry event for a non-authenticated visitor.
     */
    public function store(Request $request): JsonResponse|Response
    {
        if (Auth::check()) {
            return response()->noContent();
        }

        $data = $request->validate([
            'landing_page' => 'nullable|string|max:2048',
            'referrer' => 'nullable|string|max:2048',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
        ]);

        $now = now();

        $visit = AnonymousVisit::create([
            'visit_token' => (string) Str::uuid(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'ip_address' => $request->ip(),
            'ip_data' => $this->ipData($request),
            'user_agent' => $request->userAgent(),
            'referrer' => $data['referrer'] ?? $request->headers->get('referer'),
            'landing_page' => $data['landing_page'] ?? $request->fullUrl(),
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'entered_at' => $now,
            'last_seen_at' => $now,
        ]);

        return response()->json([
            'visit_token' => $visit->visit_token,
        ], 201);
    }

    /**
     * Store the exit event for a previously-created anonymous visit.
     */
    public function update(Request $request): Response
    {
        $data = $request->validate([
            'visit_token' => 'required|uuid',
            'exit_page' => 'nullable|string|max:2048',
        ]);

        $visit = AnonymousVisit::where('visit_token', $data['visit_token'])->first();

        if (! $visit) {
            return response()->noContent();
        }

        $exitedAt = now();
        $durationSeconds = $visit->entered_at
            ? max(0, $visit->entered_at->diffInSeconds($exitedAt))
            : null;

        $visit->update([
            'exit_page' => $data['exit_page'] ?? null,
            'last_seen_at' => $exitedAt,
            'exited_at' => $exitedAt,
            'duration_seconds' => $durationSeconds,
        ]);

        return response()->noContent();
    }

    /**
     * Capture IP details exposed by the current request/proxy without relying on a remote lookup service.
     */
    private function ipData(Request $request): array
    {
        return array_filter([
            'address' => $request->ip(),
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'forwarded_for' => $this->header($request, 'x-forwarded-for'),
            'real_ip' => $this->header($request, 'x-real-ip'),
            'cf_connecting_ip' => $this->header($request, 'cf-connecting-ip'),
            'cf_ipcountry' => $this->header($request, 'cf-ipcountry'),
            'cloudfront_country' => $this->header($request, 'cloudfront-viewer-country'),
            'vercel_country' => $this->header($request, 'x-vercel-ip-country'),
            'vercel_region' => $this->header($request, 'x-vercel-ip-country-region'),
            'vercel_city' => $this->decodedHeader($request, 'x-vercel-ip-city'),
            'vercel_timezone' => $this->header($request, 'x-vercel-ip-timezone'),
        ], fn ($value) => filled($value));
    }

    private function header(Request $request, string $name): ?string
    {
        $value = $request->headers->get($name);

        return filled($value) ? trim($value) : null;
    }

    private function decodedHeader(Request $request, string $name): ?string
    {
        $value = $this->header($request, $name);

        return $value ? rawurldecode($value) : null;
    }
}
