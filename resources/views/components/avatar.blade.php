@php
    /** @var \App\Models\User $user */
    $size = (int) ($size ?? 32);
    $hue = (($user->id ?? 0) * 47) % 360;
    $initial = mb_substr(mb_trim((string) $user->name), 0, 1) ?: '?';
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 32 32" class="inline-block shrink-0 rounded-full align-middle" role="img" aria-label="{{ $user->name }}">
    <circle cx="16" cy="16" r="16" fill="hsl({{ $hue }}, 62%, 42%)"/>
    <text x="16" y="21.5" text-anchor="middle" font-size="15" font-weight="bold" fill="#ffffff" font-family="system-ui, -apple-system, sans-serif">{{ $initial }}</text>
</svg>
