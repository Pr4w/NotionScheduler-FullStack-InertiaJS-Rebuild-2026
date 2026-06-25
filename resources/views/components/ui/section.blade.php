{{--
    Section wrapper — keeps vertical rhythm + container width consistent
    across the landing page and every future solution/blog page.

    Props:
      id     — anchor id (for the one-page nav)
      width  — narrow | default | wide
      pad    — default | tight | none
--}}

@props([
    'id' => null,
    'width' => 'default',
    'pad' => 'default',
])

@php
    $widths = [
        'narrow'  => 'max-w-3xl',
        'default' => 'max-w-6xl',
        'wide'    => 'max-w-7xl',
    ];
    $pads = [
        'default' => 'py-20 sm:py-28',
        'tight'   => 'py-12 sm:py-16',
        'none'    => '',
    ];
@endphp

<section @if($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => $pads[$pad] ?? $pads['default']]) }}>
    <div class="mx-auto {{ $widths[$width] ?? $widths['default'] }} px-5 sm:px-8">
        {{ $slot }}
    </div>
</section>