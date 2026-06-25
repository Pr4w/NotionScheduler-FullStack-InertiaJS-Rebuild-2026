{{--
    Button / link primitive.

    Props:
      href     — if set, renders <a>, otherwise <button>
      variant  — primary | dark | ghost | white
      size     — sm | md | lg
      icon     — optional: shows a trailing arrow

    Examples:
      <x-ui.button href="/app/register">Get started</x-ui.button>
      <x-ui.button variant="dark" size="lg" icon>Start free</x-ui.button>
--}}

@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'icon' => false,
])

@php
    $base = 'group inline-flex items-center justify-center gap-2 font-semibold rounded-full border-2 border-ink btn-pop select-none whitespace-nowrap';

    $variants = [
        'primary' => 'bg-flare-500 text-paper hover:bg-flare-600',
        'dark'    => 'bg-ink text-paper hover:bg-black',
        'white'   => 'bg-paper text-ink hover:bg-white',
        'ghost'   => 'bg-transparent text-ink border-transparent shadow-none hover:bg-paper-sink',
    ];

    $sizes = [
        'sm' => 'text-sm px-4 py-2',
        'md' => 'text-base px-6 py-3',
        'lg' => 'text-lg px-8 py-4',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']));

    if ($variant === 'ghost') {
        $classes = str_replace('btn-pop', '', $classes);
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @if ($icon)
            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>
        {{ $slot }}
        @if ($icon)
            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
    </button>
@endif