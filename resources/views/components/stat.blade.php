@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'icon' => null])

<div class="card flex min-w-0 flex-col gap-2 p-4 transition-colors duration-150 hover:border-brand-100 sm:flex-row sm:items-start sm:gap-3 sm:p-5">
    @if ($icon)
        <span @class([
            'flex size-9 shrink-0 items-center justify-center rounded-md',
            'bg-slate-100 text-slate-600' => $tone === 'default',
            'bg-brand-50 text-brand-600' => $tone === 'brand',
            'bg-red-50 text-red-600' => $tone === 'danger',
            'bg-green-50 text-green-700' => $tone === 'success',
        ])>
            <x-icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <div class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase sm:text-xs">{{ $label }}</div>
        <div @class([
            'mt-1 text-lg leading-tight font-bold tabular-nums wrap-anywhere sm:text-xl 2xl:text-2xl',
            'text-slate-900' => $tone === 'default',
            'text-brand-700' => $tone === 'brand',
            'text-red-600' => $tone === 'danger',
            'text-green-700' => $tone === 'success',
        ])>{{ $value }}</div>
        @if ($hint)
            <div class="mt-1 text-xs wrap-anywhere text-slate-500">{{ $hint }}</div>
        @endif
    </div>
</div>
