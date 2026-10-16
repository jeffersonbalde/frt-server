<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        return view('devices.index', [
            'devices' => Device::query()
                ->with('lastUser')
                ->withCount(['transactions' => fn ($query) => $query->where('status', 'valid')])
                ->orderBy('code')
                ->get(),
        ]);
    }
}
