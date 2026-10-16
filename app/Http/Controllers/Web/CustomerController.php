<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));

        $customers = Customer::query()
            ->withCount(['transactions' => fn ($query) => $query->where('status', 'valid')])
            ->withSum(['transactions' => fn ($query) => $query->where('status', 'valid')], 'gross')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('contact_no', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', ['customers' => $customers, 'search' => $search]);
    }

    public function show(Customer $customer): View
    {
        $transactions = $customer->transactions()->with(['device', 'recorder'])->orderByDesc('recorded_at')->paginate(25);

        return view('customers.show', ['customer' => $customer, 'transactions' => $transactions]);
    }
}
