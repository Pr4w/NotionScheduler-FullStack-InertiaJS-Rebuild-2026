<x-filament-panels::page>
    @php($user = $this->userRecord)

    @if (! $this->user || ! $user)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No user selected. Open this tool from the <strong>Inspect</strong> action on the Users table.
            </p>
        </x-filament::section>
    @else
        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button type="button" wire:click="backToDatabases" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                {{ $user->username }}
            </button>
            @if ($this->database)
                @svg('heroicon-m-chevron-right', 'h-4 w-4 text-gray-400')
                <button type="button" wire:click="backToPosts" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                    {{ $this->database->database_name ?? 'Database #'.$this->database->id }}
                </button>
            @endif
            @if ($this->post)
                @svg('heroicon-m-chevron-right', 'h-4 w-4 text-gray-400')
                <span class="font-medium">{{ $this->post->post_name ?? 'Post #'.$this->post->id }}</span>
            @endif
        </div>

        {{-- User panel (always shown) --}}
        <x-filament::section>
            <x-slot name="heading">User</x-slot>
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 lg:grid-cols-4">
                @php($fields = [
                    'ID' => $user->id,
                    'Username' => $user->username,
                    'Email' => $user->email,
                    'Plan' => $user->getTier(),
                    'Active' => $user->is_active ? 'Yes' : 'No',
                    'Wizard done' => $user->completed_wizard ? 'Yes' : 'No',
                    'Joined' => optional($user->created_at)->toDateString(),
                    'Affiliate parent' => $user->affiliate_parent ?: '—',
                ])
                @foreach ($fields as $label => $value)
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="truncate font-medium" title="{{ $value }}">{{ $value !== null && $value !== '' ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        {{-- LEVEL 1: databases --}}
        @if (! $this->databaseId)
            <x-filament::section>
                <x-slot name="heading">Databases ({{ $this->databases->count() }})</x-slot>
                @if ($this->databases->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">This user has no databases.</p>
                @else
                    <div class="-my-2 divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($this->databases as $db)
                            <button type="button" wire:click="selectDatabase({{ $db->id }})"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $db->database_name ?? 'Untitled database' }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        #{{ $db->id }} · {{ $db->socials_count }} account{{ $db->socials_count === 1 ? '' : 's' }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-filament::badge :color="$db->is_valid ? 'success' : 'danger'" size="sm">
                                        {{ $db->is_valid ? 'valid' : 'invalid' }}
                                    </x-filament::badge>
                                    @svg('heroicon-m-chevron-right', 'h-4 w-4 text-gray-400')
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

        {{-- LEVEL 2: posts in the database --}}
        @elseif (! $this->postId)
            <x-filament::section>
                <x-slot name="heading">Posts ({{ $this->posts->count() }})</x-slot>
                <x-slot name="description">Imported posts for this database, newest first.</x-slot>

                @if ($this->posts->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No posts imported for this database.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="py-2 pr-4 font-medium">Post</th>
                                    <th class="py-2 pr-4 font-medium">Platform</th>
                                    <th class="py-2 pr-4 font-medium">Status</th>
                                    <th class="py-2 pr-4 font-medium">Scheduled</th>
                                    <th class="py-2 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($this->posts as $p)
                                    @php($color = match(true) {
                                        $p->status === 'posted' => 'success',
                                        $p->status === 'error' => 'danger',
                                        in_array($p->status, ['processing', 'slow_processing', 'processing_part2']) => 'warning',
                                        default => 'info',
                                    })
                                    <tr wire:click="selectPost({{ $p->id }})" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="py-2.5 pr-4 font-medium">{{ $p->post_name ?? 'Untitled' }} <span class="text-xs text-gray-400">#{{ $p->id }}</span></td>
                                        <td class="py-2.5 pr-4 capitalize">{{ $p->platform }}</td>
                                        <td class="py-2.5 pr-4"><x-filament::badge :color="$color" size="sm">{{ $p->status }}</x-filament::badge></td>
                                        <td class="whitespace-nowrap py-2.5 pr-4 text-gray-500 dark:text-gray-400">{{ $p->scheduled_date ?? '—' }}</td>
                                        <td class="py-2.5 text-right">@svg('heroicon-m-chevron-right', 'ml-auto h-4 w-4 text-gray-400')</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

        {{-- LEVEL 3: post detail --}}
        @else
            @php($p = $this->post)
            <x-filament::section>
                <x-slot name="heading">Post detail</x-slot>
                @if (! $p)
                    <p class="text-sm text-gray-500 dark:text-gray-400">Post not found (it may have been removed).</p>
                @else
                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 lg:grid-cols-4">
                        @php($detail = [
                            'ID' => $p->id,
                            'Name' => $p->post_name ?? '—',
                            'Platform' => $p->platform,
                            'Status' => $p->status,
                            'Scheduled' => $p->scheduled_date ?? '—',
                            'Posted' => $p->posted_date ?? '—',
                            'In flight' => $p->in_flight ? 'Yes' : 'No',
                            'Foreign id' => $p->posted_foreign_id ?: '—',
                            'Notion page id' => $p->post_page_id ?: '—',
                            'Account id' => $p->account_id ?: '—',
                            'Is story' => $p->platform_is_story ? 'Yes' : 'No',
                            'Valid / active' => ($p->is_valid ? 'valid' : 'invalid').' / '.($p->is_active ? 'active' : 'inactive'),
                        ])
                        @foreach ($detail as $label => $value)
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="truncate font-medium" title="{{ $value }}">{{ $value !== null && $value !== '' ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-filament::section>

            {{-- Live Notion fetch --}}
            @if ($p)
                <x-filament::section>
                    <x-slot name="heading">Notion content</x-slot>
                    <x-slot name="description">Live fetch of this post's page content, media, thumbnail and date from Notion.</x-slot>

                    <div class="mb-4">
                        <x-filament::button wire:click="fetchNotionContent" wire:target="fetchNotionContent" wire:loading.attr="disabled" icon="heroicon-m-arrow-path">
                            <span wire:loading.remove wire:target="fetchNotionContent">Fetch from Notion</span>
                            <span wire:loading wire:target="fetchNotionContent">Fetching…</span>
                        </x-filament::button>
                    </div>

                    @if ($notionLoaded)
                        @if ($notionError)
                            <div class="text-sm font-medium text-danger-600 dark:text-danger-400">{{ $notionError }}</div>
                        @elseif ($notion)
                            <div class="flex flex-col gap-4">
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Scheduled date (from Notion)</div>
                                    <div class="font-medium">{{ $notion['date'] ?? '—' }}</div>
                                </div>

                                <div>
                                    <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">Content</div>
                                    <pre class="max-h-80 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs dark:bg-white/5">{{ trim($notion['content'] ?? '') ?: '(empty)' }}</pre>
                                </div>

                                <div>
                                    <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">Media ({{ is_array($notion['media']) ? count($notion['media']) : 0 }})</div>
                                    @if (! empty($notion['media']))
                                        <ul class="list-disc space-y-1 pl-5 text-sm">
                                            @foreach ($notion['media'] as $m)
                                                <li>
                                                    @if (is_array($m) && isset($m['url']))
                                                        <a href="{{ $m['url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">{{ \Illuminate\Support\Str::limit($m['url'], 80) }}</a>
                                                        <span class="text-xs text-gray-400">{{ $m['extension'] ?? '' }}</span>
                                                    @else
                                                        <span class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit(json_encode($m), 120) }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                    @endif
                                </div>

                                @if (! empty($notion['thumbnail']))
                                    <div>
                                        <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">Thumbnail</div>
                                        @php($thumbUrl = is_array($notion['thumbnail']) ? ($notion['thumbnail']['url'] ?? null) : $notion['thumbnail'])
                                        @if ($thumbUrl)
                                            <a href="{{ $thumbUrl }}" target="_blank" rel="noopener" class="text-sm text-primary-600 hover:underline dark:text-primary-400">{{ \Illuminate\Support\Str::limit($thumbUrl, 80) }}</a>
                                        @else
                                            <pre class="overflow-auto rounded-lg bg-gray-50 p-2 text-xs dark:bg-white/5">{{ json_encode($notion['thumbnail'], JSON_PRETTY_PRINT) }}</pre>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif
                </x-filament::section>
            @endif
        @endif
    @endif
</x-filament-panels::page>
