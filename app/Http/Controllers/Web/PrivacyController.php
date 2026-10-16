<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;

class PrivacyController extends Controller
{
    /**
     * The public privacy policy for the FRT app and dashboard (linked from Google Play).
     */
    public function __invoke(): View
    {
        return view('privacy', ['business' => Setting::values()]);
    }
}
