<div class="p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink">Audit Log</h1>
            <p class="text-xs text-muted mt-0.5">Append-only record of every sensitive action across the newsroom</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-panel border border-border rounded-xl p-4 mb-4">
        <div class="grid grid-cols-5 gap-3">
            <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1">Action</label>
                <select wire:model.live="filterAction" class="w-full text-xs border border-border rounded-lg p-2">
                    <option value="">All actions</option>
                    @foreach(\App\Models\StoryEvent::ACTIONS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                    <option value="role.updated">Role updated</option>
                    <option value="ai.kill_switch.on">AI kill switch on</option>
                    <option value="ai.kill_switch.off">AI kill switch off</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1">Entity Type</label>
                <select wire:model.live="filterEntityType" class="w-full text-xs border border-border rounded-lg p-2">
                    <option value="">All types</option>
                    <option value="Story">Story</option>
                    <option value="role">Role</option>
                    <option value="ai">AI</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1">Entity ID</label>
                <input type="text" wire:model.live.debounce.300ms="filterEntityId" placeholder="e.g. 42" class="w-full text-xs border border-border rounded-lg p-2">
            </div>
            <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1">From Date</label>
                <input type="date" wire:model.live="filterDateFrom" class="w-full text-xs border border-border rounded-lg p-2">
            </div>
            <div>
                <label class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1">To Date</label>
                <input type="date" wire:model.live="filterDateTo" class="w-full text-xs border border-border rounded-lg p-2">
            </div>
        </div>
    </div>

    {{-- Results table --}}
    <div class="bg-panel border border-border rounded-xl overflow-hidden">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-border bg-gray-50 text-left">
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">Time</th>
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">Actor</th>
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">Action</th>
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">Entity</th>
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">Diff</th>
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">IP</th>
                    <th class="px-4 py-2 font-bold text-muted uppercase tracking-wider">Correlation</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->results as $log)
                    <tr class="border-b border-border hover:bg-gray-50">
                        <td class="px-4 py-2 text-muted whitespace-nowrap">{{ $log->created_at?->timezone('Asia/Dhaka')->format('M j, h:i A') }}</td>
                        <td class="px-4 py-2 font-semibold text-ink">
                            @if($log->actor_type === 'user' && $log->actor_id)
                                User #{{ $log->actor_id }}
                            @else
                                <span class="text-muted italic">{{ $log->actor_type }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @php
                                $actionLabel = \App\Models\StoryEvent::ACTIONS[$log->action] ?? $log->action;
                                $actionColor = match($log->action) {
                                    'published', 'auto_published' => 'bg-green-100 text-green-700',
                                    'killed' => 'bg-red-100 text-red-700',
                                    'handover' => 'bg-amber-100 text-amber-700',
                                    'restored' => 'bg-purple-100 text-purple-700',
                                    'ai_applied' => 'bg-blue-100 text-blue-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold {{ $actionColor }}">{{ $actionLabel }}</span>
                        </td>
                        <td class="px-4 py-2">
                            @if($log->entity_type === 'Story' && $log->entity_id)
                                <a href="{{ route('admin.story', \App\Models\Story::find($log->entity_id)?->public_id ?? '') }}" class="text-navy-800 hover:underline">
                                    Story #{{ $log->entity_id }}
                                </a>
                            @else
                                {{ $log->entity_type }}{{ $log->entity_id ? " #{$log->entity_id}" : '' }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-muted max-w-[200px] truncate">
                            @if($log->diff)
                                {{ \Illuminate\Support\Str::limit(json_encode($log->diff), 80) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-muted font-mono text-[10px]">{{ $log->ip ?? '—' }}</td>
                        <td class="px-4 py-2 text-muted font-mono text-[10px]">{{ $log->correlation_id ? \Illuminate\Support\Str::limit($log->correlation_id, 12) : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-muted italic">No audit log entries match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $this->results->links() }}
    </div>
</div>
