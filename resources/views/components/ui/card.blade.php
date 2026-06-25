{{--
    Card surface. The "pop" variant gets the hard offset shadow + ink border.

    Props:
      pop      — bool, hard sticker shadow
      tone     — paper | sink | ink | flare
      as       — html tag (default div)

    Example:
      <x-ui.card pop tone="paper">...</x-ui.card>
--}}

@props([
    'pop' => false,
    'tone' => 'paper',
    'as' => 'div',
])

@php
    $tones = [
        'paper' => 'bg-paper',
        'sink'  => 'bg-paper-sink',
        'ink'   => 'bg-ink text-paper',
        'flare' => 'bg-flare-500 text-paper',
    ];

    $classes = 'rounded-[var(--radius-card)] '
        .($tones[$tone] ?? $tones['paper'])
        .($pop ? ' border-2 border-ink card-pop' : ' border border-line');
@endphp

<{{ $as }} {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</{{ $as }}>