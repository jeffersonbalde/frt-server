@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'icon' => null])

<div class="card flex items-start gap-3 transition-colors duration-150 hover:border-brand-100">
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
        <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $label }}</div>
        <div @class([
            'mt-1 text-lg font-bold tabular-nums sm:text-xl 2xl:text-2xl',
            'text-slate-900' => $tone === 'default',
            'text-brand-700' => $tone === 'brand',
            'text-red-600' => $tone === 'danger',
            'text-green-700' => $tone === 'success',
        ])>{{ $value }}</div>
        @if ($hint)
            <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
        @endif
    </div>
</div>
