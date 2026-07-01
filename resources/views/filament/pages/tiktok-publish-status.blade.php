<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Check a TikTok publish status</x-slot>
        <x-slot name="description">
            Enter the TikTok <code>publish_id</code> (stored on the post as
            <code>posted_foreign_id</code>). We'll find the owning account and
            query TikTok's <code>post/publish/status/fetch/</code> endpoint.
        </x-slot>

        <form wire:submit="check" class="flex flex-col gap-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="publishId"
                    placeholder="v_pub_url~v2-1.765..."
                    autocomplete="off"
                />
            </x-filament::input.wrapper>

            <div>
                <x-filament::button type="submit" wire:target="check" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="check">Check status</span>
                    <span wire:loading wire:target="check">Checking…</span>
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($checked)
        @if ($error)
            <x-filament::section>
                <div class="text-sm font-medium text-danger-600 dark:text-danger-400">
                    {{ $error }}
                </div>
            </x-filament::section>
        @else
            @if ($context)
                <x-filament::section>
                    <x-slot name="heading">Post</x-slot>
                    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        @foreach ($context as $label => $value)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">TikTok response</x-slot>
                <x-slot name="headerEnd">
                    @if ($httpStatus)
                        <x-filament::badge :color="$httpStatus >= 200 && $httpStatus < 300 ? 'success' : 'danger'">
                            HTTP {{ $httpStatus }}
                        </x-filament::badge>
                    @endif
                </x-slot>

                <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100">{{ json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
