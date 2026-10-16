<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoidController extends Controller
{
    /**
     * Every voided transaction: what, when, why, and who voided it (with the owner's approval code).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $voids = Transaction::query()
            ->where('status', 'void')
            ->with(['device', 'recorder', 'voider'])
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('business_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('business_date', '<=', $to))
            ->orderByDesc('voided_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('voids.index', [
            'voids' => $voids,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ]);
    }
}
