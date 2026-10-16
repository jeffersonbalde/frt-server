<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['settings' => Setting::values(), 'voidCode' => Setting::voidCode()]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Number inputs are shown with thousands commas ("1,000.50"); strip them before validating.
        $request->merge(collect([...Setting::MONEY_KEYS, ...Setting::PERCENT_KEYS])
            ->filter(fn (string $key) => is_string($request->input($key)))
            ->mapWithKeys(fn (string $key) => [$key => str_replace([',', '₱', ' '], '', $request->input($key))])
            ->all());

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_contact' => ['required', 'string', 'max:50'],
            'messenger' => ['nullable', 'string', 'max:255'],
            'rice_rate' => ['required', 'numeric', 'gt:0', 'max:10000', 'decimal:0,2'],
            'tahop_buy_rate' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'tahop_sell_rate' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'fuel_price' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'labor_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'fuel_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'rice_rate.gt' => 'The rice price per kg must be more than zero.',
            ...$this->numberMessages(),
        ], [
            'rice_rate' => 'rice price per kg',
            'tahop_buy_rate' => 'tahop buying price',
            'tahop_sell_rate' => 'tahop selling price',
            'fuel_price' => 'oil/gas price',
            'labor_pct' => 'labor percentage',
            'fuel_pct' => 'fuel percentage',
        ]);

        if ((float) $validated['labor_pct'] + (float) $validated['fuel_pct'] > 100) {
            return back()->withInput()->withErrors(['labor_pct' => 'Labor and fuel percentages together cannot exceed 100%.']);
        }

        foreach (Setting::MONEY_KEYS as $key) {
            $validated[$key] = (int) round((float) $validated[$key] * 100);
        }

        $validated['messenger'] ??= '';

        Setting::store($validated);

        return redirect()->route('settings.edit')->with('status', 'Settings saved. Phones use the new defaults on their next sync and next Start of Day.');
    }

    /**
     * Set or change the owner's approval code, entered on the phone to void a transaction or reopen a closed day.
     */
    public function updateVoidCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'void_code' => ['required', 'digits:6', 'confirmed'],
        ], [
            'void_code.digits' => 'The approval code must be exactly 6 digits.',
            'void_code.confirmed' => 'The two codes do not match.',
        ], [
            'void_code' => 'approval code',
        ]);

        Setting::setVoidCode($validated['void_code']);

        return redirect()->route('settings.edit')->with('status', 'Owner approval code saved. Phones get it on their next sync.');
    }

    /**
     * Friendly messages for the number fields, e.g. "The rice price per kg is too high (max 10,000)."
     *
     * @return array<string, string>
     */
    private function numberMessages(): array
    {
        $messages = [];

        foreach (Setting::MONEY_KEYS as $key) {
            $messages["$key.max"] = 'The :attribute is too high (maximum ₱10,000).';
            $messages["$key.decimal"] = 'The :attribute can have at most 2 decimal places.';
            $messages["$key.numeric"] = 'The :attribute must be a number, e.g. 1,250.50.';
        }

        foreach (Setting::PERCENT_KEYS as $key) {
            $messages["$key.max"] = 'The :attribute cannot be more than 100%.';
            $messages["$key.numeric"] = 'The :attribute must be a number, e.g. 30.';
        }

        return $messages;
    }
}
