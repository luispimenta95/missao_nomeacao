<?php

namespace App\Http\Controllers;

use App\Models\AnonymousVisit;
use Carbon\Carbon;

class AnonymousVisitAdminController extends Controller
{
    /**
     * Display anonymous visits captured from the public site.
     */
    public function index()
    {
        $visits = AnonymousVisit::orderBy('entered_at', 'desc')->paginate(15);

        $totalVisits = AnonymousVisit::count();
        $activeVisits = AnonymousVisit::whereNull('exited_at')->count();
        $visitsToday = AnonymousVisit::whereDate('entered_at', Carbon::today())->count();

        return view('admin.anonymous-visits.index', compact(
            'visits',
            'totalVisits',
            'activeVisits',
            'visitsToday'
        ));
    }
}
