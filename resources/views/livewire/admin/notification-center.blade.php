<div class="p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink">Notifications</h1>
            <p class="text-xs text-muted mt-0.5">Editorial alerts, review requests, and workflow updates</p>
        </div>
        @if($unreadCount > 0)
        <button wire:click="markAllRead" class="text-xs font-semibold text-crimson hover:underline">
            Mark all as read ({{ $unreadCount }})
        </button>
        @endif
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 mb-4">
        <button wire:click="$set('filter', 'all')"
            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors {{ $filter === 'all' ? 'bg-navy-800 text-white border-navy-800' : 'bg-white text-muted border-border hover:border-navy-800' }}">
            All
        </button>
        <button wire:click="$set('filter', 'unread')"
            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors {{ $filter === 'unread' ? 'bg-navy-800 text-white border-navy-800' : 'bg-white text-muted border-border hover:border-navy-800' }}">
            Unread @if($unreadCount > 0)<span class="ml-1 bg-crimson text-white px-1.5 rounded-full text-[10px]">{{ $unreadCount }}</span>@endif
        </button>
    </div>

    {{-- Notification list --}}
    <div class="bg-white border border-border rounded-xl divide-y divide-border">
        @forelse($notifications as $n)
            @php
                $event = $n->data['event'] ?? '';
                $data = $n->data['data'] ?? [];
                $link = \App\Livewire\Admin\NotificationCenter::deepLink($data, $event);
                $dotColor = match($event) {
                    'review_requested', 'handover', 'changes_requested', 'media_reedit' => 'bg-amber',
                    'published', 'approved', 'media_approved' => 'bg-green',
                    'killed', 'media_rejected' => 'bg-crimson',
                    default => 'bg-blue',
                };
                $label = match($event) {
                    'review_requested' => 'Story sent for review',
                    'approved' => 'Story approved',
                    'changes_requested' => 'Changes requested',
                    'published' => 'Story published',
                    'killed' => 'Story killed',
                    'handover' => 'Story ownership transferred',
                    'note_added' => 'New note on story',
                    'media_approved' => 'Media approved',
                    'media_rejected' => 'Media rejected',
                    'media_reedit' => 'Re-edit requested',
                    default => ucfirst(str_replace('_', ' ', $event)),
                };
            @endphp
            <a href="{{ $link }}" wire:click="markRead('{{ $n->id }}')"
               class="flex gap-3 items-start px-4 py-3.5 transition-colors hover:bg-paper/60 {{ is_null($n->read_at) ? 'bg-paper' : '' }}">
                <span class="w-2 h-2 rounded-full mt-[7px] shrink-0 {{ $dotColor }}"></span>
                <div class="flex-1 min-w-0">
                    <div class="text-[13px] font-semibold text-ink leading-snug">{{ $label }}</div>
                    @if(!empty($data['headline']))
                        <div class="text-[12.5px] text-muted leading-snug mt-0.5 truncate">{{ $data['headline'] }}</div>
                    @endif
                    <div class="text-[11px] text-muted-2 mt-1">{{ $n->created_at->diffForHumans() }}</div>
                </div>
                @if(is_null($n->read_at))
                    <span class="w-2 h-2 rounded-full bg-crimson shrink-0 mt-[7px]" title="Unread"></span>
                @endif
            </a>
        @empty
            <div class="text-center text-sm text-muted py-12">
                <x-lucide-bell-off class="w-8 h-8 mx-auto text-muted-2 mb-2" stroke-width="1.5" />
                {{ $filter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' }}
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
</div>
