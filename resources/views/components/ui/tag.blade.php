{{--
    Sticker tag — the little rotated "label" used as a section eyebrow.

    Props:
      tone    — flare | mint | ink | paper
      rotate  — bool, applies the playful tilt

    Example:
      <x-ui.tag tone="flare" rotate>Why bother?</x-ui.tag>
--}}

@props([
    'tone' => 'flare',
    'rotate' => false,
])

@php
    $tones = [
        'flare' => 'bg-flare-100 text-flare-700 border-flare-300',
        'mint'  => 'bg-mint-100 text-mint-500 border-mint-500/40',
        'ink'   => 'bg-ink text-paper border-ink',
        'paper' => 'bg-paper text-ink border-line',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wider '
        .($tones[$tone] ?? $tones['flare'])
        .($rotate ? ' tag-rotate' : ''),
]) }}>
    {{ $slot }}
</span>