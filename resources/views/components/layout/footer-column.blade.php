{{-- Footer link column. Keeps the footer markup tidy. --}}

@props(['title'])

<div>
    <h3 class="mb-4 font-display text-xs font-bold uppercase tracking-widest text-ink">
        {{ $title }}
    </h3>
    <ul class="space-y-2.5">
        {{ $slot }}
    </ul>
</div>