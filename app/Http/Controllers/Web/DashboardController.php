<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DaySession;
use App\Models\Device;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        $today = today();

        return view('dashboard', [
            'today' => $reports->summary($today, $today),
            'month' => $reports->summary($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'tahop' => $reports->tahopStock(),
            'devices' => Device::query()->with('lastUser')->orderBy('code')->get(),
            'openSessions' => DaySession::query()->with('device')->where('status', 'open')->orderByDesc('business_date')->get(),
            'varianceSessions' => DaySession::query()
                ->with('device')
                ->where('status', 'closed')
                ->where('variance', '!=', 0)
                ->orderByDesc('business_date')
                ->limit(10)
                ->get(),
        ]);
    }
}
