@php
    /**
     * Colorful stat card with a gradient icon chip.
     * Usage: <x-stat-card :label="..." :value="..." icon="banknotes" color="emerald" :sub="..." />
     */
    $chips = [
        'emerald' => 'from-emerald-500 to-teal-600 shadow-emerald-500/30',
        'green'   => 'from-green-500 to-emerald-600 shadow-green-500/30',
        'blue'    => 'from-blue-500 to-indigo-600 shadow-blue-500/30',
        'indigo'  => 'from-indigo-500 to-violet-600 shadow-indigo-500/30',
        'violet'  => 'from-violet-500 to-purple-600 shadow-violet-500/30',
        'orange'  => 'from-orange-500 to-amber-600 shadow-orange-500/30',
        'rose'    => 'from-rose-500 to-pink-600 shadow-rose-500/30',
        'red'     => 'from-red-500 to-rose-600 shadow-red-500/30',
        'teal'    => 'from-teal-500 to-cyan-600 shadow-teal-500/30',
        'cyan'    => 'from-cyan-500 to-sky-600 shadow-cyan-500/30',
        'slate'   => 'from-slate-500 to-gray-700 shadow-slate-500/30',
        'amber'   => 'from-amber-500 to-orange-600 shadow-amber-500/30',
    ];
    $chip = $chips[$color ?? 'emerald'] ?? $chips['emerald'];
@endphp
<div {{ $attributes->merge(['class' => 'card card-hover animate-fade-up']) }}>
    <div class="flex items-center gap-3">
        <div class="shrink-0 w-11 h-11 rounded-xl bg-gradient-to-br {{ $chip }} shadow-md text-white flex items-center justify-center">
            <x-icon :name="$icon ?? 'banknotes'" class="w-6 h-6" />
        </div>
        <div class="min-w-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 truncate">{{ $label }}</div>
            <div class="text-xl font-extrabold text-gray-900 leading-tight truncate {{ $valueClass ?? '' }}">{{ $value }}</div>
            @if(!empty($sub))
                <div class="text-xs text-gray-500 truncate">{!! $sub !!}</div>
            @endif
        </div>
    </div>
</div>
