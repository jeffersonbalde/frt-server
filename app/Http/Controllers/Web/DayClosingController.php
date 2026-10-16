<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DaySession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DayClosingController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate([
            'status' => ['nullable', 'in:open,closed,variance'],
        ])['status'] ?? null;

        $sessions = DaySession::query()
            ->with(['device', 'recorder'])
            ->withCount(['transactions' => fn ($query) => $query->where('status', 'valid')])
            ->when($filter === 'open', fn ($query) => $query->where('status', 'open'))
            ->when($filter === 'closed', fn ($query) => $query->where('status', 'closed'))
            ->when($filter === 'variance', fn ($query) => $query->where('status', 'closed')->where('variance', '!=', 0))
            ->orderByDesc('business_date')
            ->orderBy('device_id')
            ->paginate(25)
            ->withQueryString();

        return view('closings.index', ['sessions' => $sessions, 'filter' => $filter]);
    }

    public function show(DaySession $daySession): View
    {
        $daySession->load(['device', 'recorder', 'reopener', 'transactions' => fn ($query) => $query->with(['voider', 'recorder'])->orderBy('recorded_at')]);

        return view('closings.show', ['session' => $daySession]);
    }
}
