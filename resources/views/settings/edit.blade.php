<x-layouts.app title="Settings">
    <form method="POST" action="{{ route('settings.update') }}" class="grid max-w-4xl grid-cols-1 gap-6 lg:grid-cols-2 [&>*]:min-w-0">
        @csrf
        @method('PUT')

        <section class="card space-y-4">
            <h2 class="font-semibold text-slate-900">Receipt header</h2>
            @foreach (['business_name' => 'Business name', 'business_address' => 'Address', 'business_contact' => 'Contact', 'messenger' => 'Messenger'] as $key => $label)
                <div>
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    <input id="{{ $key }}" name="{{ $key }}" type="text" value="{{ old($key, $settings[$key]) }}" class="input" @required($key !== 'messenger')>
                    <x-field-error :name="$key" />
                </div>
            @endforeach
        </section>

        <section class="card space-y-4">
            <h2 class="font-semibold text-slate-900">Default Start of Day prices (₱)</h2>
            @foreach (['rice_rate' => 'Rice milling per kg', 'tahop_buy_rate' => 'Tahop per kg (buy)', 'tahop_sell_rate' => 'Tahop per kg (sell)', 'fuel_price' => 'Oil / gas price'] as $key => $label)
                <div>
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    <input id="{{ $key }}" name="{{ $key }}" type="text" inputmode="decimal" data-number value="{{ old($key, number_format($settings[$key] / 100, 2)) }}" class="input text-right tabular-nums" required>
                    <x-field-error :name="$key" />
                </div>
            @endforeach

            <h2 class="pt-2 font-semibold text-slate-900">Deductions from the milling amount (%)</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="labor_pct" class="label">Labor (split among operators)</label>
                    <input id="labor_pct" name="labor_pct" type="text" inputmode="decimal" data-number value="{{ old('labor_pct', $settings['labor_pct']) }}" class="input text-right tabular-nums" required>
                    <x-field-error name="labor_pct" />
                </div>
                <div>
                    <label for="fuel_pct" class="label">Diesel / other charges</label>
                    <input id="fuel_pct" name="fuel_pct" type="text" inputmode="decimal" data-number value="{{ old('fuel_pct', $settings['fuel_pct']) }}" class="input text-right tabular-nums" required>
                    <x-field-error name="fuel_pct" />
                </div>
            </div>
            <p class="text-xs text-slate-500">Changes apply to days started after the phone syncs. Days already started keep their own rates.</p>
        </section>

        <div class="lg:col-span-2">
            <button type="submit" class="btn btn-primary">Save settings</button>
        </div>
    </form>

    <form method="POST" action="{{ route('settings.void-code') }}" class="mt-8 max-w-4xl"
          data-turbo-confirm="Operators will need the new code to void a receipt or reopen a day once their phones sync."
          data-confirm-title="Change the approval code?" data-confirm-button="Save code">
        @csrf
        @method('PUT')

        <section class="card space-y-4">
            <div>
                <h2 class="font-semibold text-slate-900">Owner approval code</h2>
                <p class="mt-1 text-sm text-slate-600">
                    This 6-digit code must be entered on the phone to <strong>void a transaction</strong> or <strong>reopen a closed day</strong>,
                    so the owner approves each one. Keep it private. Phones get a new code on their next sync.
                </p>
            </div>

            @if ($voidCode)
                <p class="flex items-center gap-2 text-sm text-green-700">
                    <x-icon name="shield" class="size-5" />
                    A code is set{{ $voidCode['set_at'] ? ' (since '.\Illuminate\Support\Carbon::parse($voidCode['set_at'])->format('M j, Y h:i A').')' : '' }}. Enter a new one below to change it.
                </p>
            @else
                <p class="flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <x-icon name="alert" class="size-5 shrink-0" />
                    No code yet. Phones cannot void transactions or reopen days until you set one.
                </p>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="void_code" class="label">New 6-digit code</label>
                    <input id="void_code" name="void_code" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" class="input tabular-nums" required>
                    <x-field-error name="void_code" />
                </div>
                <div>
                    <label for="void_code_confirmation" class="label">Type the code again</label>
                    <input id="void_code_confirmation" name="void_code_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" class="input tabular-nums" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ $voidCode ? 'Change code' : 'Set code' }}</button>
        </section>
    </form>
</x-layouts.app>
