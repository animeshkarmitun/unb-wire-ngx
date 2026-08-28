<div>
<div class="flex items-center justify-between mb-6">
<div>
<div class="text-[12.5px] text-muted-2 mb-2"><a href="{{ route('dashboard') }}" class="text-muted hover:text-crimson-dark">Home</a> &nbsp;/&nbsp; Settings &nbsp;/&nbsp; Roles & access</div>
<h1 class="font-serif text-[30px] font-semibold tracking-tight">Roles & access</h1>
</div>
<div class="flex gap-2">
<x-btn variant="outline" wire:click="$set('showInviteModal', true)">Invite member</x-btn>
<x-btn variant="primary" wire:click="$set('showNewModal', true)">New role</x-btn>
</div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
<x-card><div class="font-serif text-[27px] font-bold">{{ $totalRoles }}</div><div class="text-[11.5px] text-muted mt-1">Total roles</div></x-card>
<x-card><div class="font-serif text-[27px] font-bold text-green">{{ $totalPeople }}</div><div class="text-[11.5px] text-muted mt-1">People with access</div></x-card>
<x-card><div class="font-serif text-[27px] font-bold">{{ $customRoles }}</div><div class="text-[11.5px] text-muted mt-1">Custom roles</div></x-card>
<x-card><div class="font-serif text-[27px] font-bold text-amber">{{ $pendingInvites }}</div><div class="text-[11.5px] text-muted mt-1">Pending invites</div></x-card>
</div>

<div class="flex gap-2 mb-5">
@php $tabs=['roles'=>'Roles','people'=>'People','audit'=>'Activity']; @endphp
@foreach($tabs as $k=>$label)
<button wire:click="setTab('{{ $k }}')" class="px-4 py-2 rounded-full text-sm font-medium border transition {{ $activeTab===$k ? 'bg-crimson-soft border-crimson text-crimson-dark font-bold' : 'bg-panel border-[#e3e1da] text-ink hover:border-crimson' }}">{{ $label }} <span class="ml-1 text-[10px] bg-[#f3f1ee] px-2 py-0.5 rounded-full">{{ $k==='roles'?$totalRoles:($k==='people'?$totalPeople:$audits->count()) }}</span></button>
@endforeach
</div>

@if($activeTab==='roles')
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
@foreach($roles as $idx=>$role)
@php $grad='g'.(($idx%8)+1); $permsCount=$role->permissions->filter(fn($p)=>$p->can_view||$p->can_create||$p->can_edit||$p->can_publish||$p->can_delete)->count(); $members=$role->users; @endphp
<div class="bg-panel border border-border rounded-[15px] p-[18px] flex flex-col hover:shadow-[0_10px_26px_rgba(15,23,48,0.08)] transition">
<div class="flex gap-3">
<div class="w-[42px] h-[42px] rounded-xl flex items-center justify-center text-white font-serif font-bold text-xs shrink-0 {{ $grad }}">{{ strtoupper(substr($role->name,0,2)) }}</div>
<div class="min-w-0">
<div class="font-serif text-[16px] font-bold flex items-center gap-2 flex-wrap">{{ $role->name }} @if($role->is_locked)<span class="text-[9px] uppercase tracking-wide bg-navy-800 text-white px-1.5 py-0.5 rounded">system</span>@elseif($role->type==='client')<span class="text-[9px] uppercase bg-purple-bg text-purple px-1.5 py-0.5 rounded">client</span>@else<span class="text-[9px] uppercase bg-purple-bg text-purple px-1.5 py-0.5 rounded">custom</span>@endif</div>
<div class="text-xs text-muted leading-5 truncate">{{ $role->description }}</div>
</div>
</div>
<div class="flex flex-wrap gap-1 mt-3">
@foreach($role->permissions as $p)
@if($p->can_view || $p->can_create || $p->can_edit || $p->can_publish || $p->can_delete)
<span class="text-[10.5px] font-semibold px-2 py-1 rounded-md {{ ($p->can_publish||$p->can_delete)?'bg-green-bg text-green':'bg-blue-bg text-blue' }}">{{ $p->module }}</span>
@else
<span class="text-[10.5px] font-semibold px-2 py-1 rounded-md bg-[#f3f1ee] text-muted">{{ $p->module }}</span>
@endif
@endforeach
</div>
<div class="flex items-center mt-3">
<div class="flex">
@foreach($members->take(4) as $m)
<div class="w-6 h-6 rounded-full bg-navy-800 text-white text-[9px] font-bold flex items-center justify-center border-2 border-white -ml-1 first:ml-0">{{ strtoupper(substr($m->name,0,2)) }}</div>
@endforeach
@if($members->count()>4)<span class="w-6 h-6 rounded-full bg-[#f3f1ee] text-muted text-[10px] flex items-center justify-center border-2 border-white -ml-1">+{{ $members->count()-4 }}</span>@endif
</div>
<span class="text-[11.5px] text-muted-2 ml-2">{{ $members->count() }} member{{ $members->count()!==1?'s':'' }}</span>
</div>
<div class="flex gap-2 mt-3 pt-3 border-t border-[#f4f2ee]">
<button wire:click="openEdit({{ $role->id }})" class="flex-1 py-1.5 text-xs font-semibold border border-[#e3e1da] rounded-lg hover:border-navy-800">Edit</button>
<button wire:click="confirmDelete({{ $role->id }})" class="flex-1 py-1.5 text-xs font-semibold border border-[#e3e1da] rounded-lg hover:border-crimson hover:text-crimson-dark hover:bg-crimson-soft">Delete</button>
</div>
</div>
@endforeach
<button wire:click="$set('showNewModal', true)" class="border-2 border-dashed border-[#d8d5cd] rounded-[15px] min-h-[220px] flex flex-col items-center justify-center gap-2 text-muted hover:border-crimson hover:text-crimson-dark hover:bg-crimson-soft transition"><x-lucide-plus class="w-5 h-5"/><span class="text-sm font-semibold">Create role</span></button>
</div>
<div class="mt-5 bg-blue-bg border border-[#cfdcf7] rounded-xl p-4 text-xs text-[#2c4a8a] flex gap-3"><x-lucide-info class="w-4 h-4 shrink-0 text-blue"/><div><strong>Client roles</strong> control the portal, not this panel. A person needs <strong>View</strong> on a module to see it in sidebar.</div></div>
@endif

@if($activeTab==='people')
<div class="bg-panel border border-border rounded-xl overflow-hidden">
<div class="grid grid-cols-[40px_1.6fr_1fr_170px_110px] gap-3 px-4 py-2.5 bg-[#fbfaf7] text-[10.5px] font-bold uppercase tracking-wide text-muted-2">
<span></span><span>Name</span><span>Desk</span><span>Role</span><span>Status</span>
</div>
@foreach($people as $p)
<div class="grid grid-cols-[40px_1.6fr_1fr_170px_110px] gap-3 items-center px-4 py-3 border-t border-border hover:bg-[#fbfaf7]">
<div class="w-8 h-8 rounded-lg bg-navy-800 text-white text-xs font-bold flex items-center justify-center">{{ strtoupper(substr($p->name,0,2)) }}</div>
<div><div class="text-sm font-semibold">{{ $p->name }}</div><div class="text-xs text-muted-2">{{ $p->email }}</div></div>
<div class="text-xs text-muted">{{ $p->desk ?? '—' }}</div>
<div class="text-xs font-medium">{{ $p->role->name ?? '—' }}</div>
<span class="text-[11px] font-bold px-2.5 py-1 rounded-full w-fit {{ $p->status==='active'?'bg-green-bg text-green':($p->status==='invited'?'bg-[#fdf3e0] text-[#b7791f]':'bg-[#f3f1ee] text-muted') }}">{{ $p->status }}</span>
</div>
@endforeach
</div>
@endif

@if($activeTab==='audit')
<div class="bg-panel border border-border rounded-xl p-2 px-5">
@forelse($audits as $a)
<div class="flex gap-3 py-3 border-b border-border last:border-0">
<span class="w-2 h-2 rounded-full bg-blue mt-1.5 shrink-0"></span>
<div><div class="text-sm"><b>{{ $a->actor_type }}</b> {{ $a->action }} @if($a->entity_type)<b>{{ $a->entity_type }} #{{ $a->entity_id }}</b>@endif</div><div class="text-xs text-muted-2">{{ $a->created_at }}</div></div>
</div>
@empty
<div class="text-sm text-muted p-4">No activity yet.</div>
@endforelse
</div>
@endif

{{-- Drawer --}}
@if($editingRoleId)
<div class="fixed inset-0 z-[70] flex justify-end">
<div class="absolute inset-0 bg-navy-900/50 backdrop-blur-sm" wire:click="closeEdit"></div>
<div class="relative w-[min(620px,100%)] bg-paper h-full overflow-y-auto shadow-2xl">
<div class="bg-panel border-b border-border p-5 flex items-center gap-3">
<div class="w-11 h-11 rounded-xl bg-navy-800 text-white flex items-center justify-center font-serif font-bold">{{ strtoupper(substr($editName,0,2)) }}</div>
<div><div class="font-serif text-lg font-bold">{{ $editName }}</div><div class="text-xs text-muted">{{ $editDesc }}</div></div>
<button wire:click="closeEdit" class="ml-auto w-8 h-8 rounded-lg border border-border bg-white">✕</button>
</div>
<div class="p-5 space-y-4">
<div class="bg-panel border border-border rounded-xl p-4">
<label class="text-xs font-semibold">Role name</label>
<input wire:model="editName" class="mt-1 w-full border border-[#e3e1da] rounded-lg px-3 py-2 text-sm bg-paper focus:bg-white focus:border-navy-800 outline-none">
<label class="text-xs font-semibold mt-3 block">Description</label>
<textarea wire:model="editDesc" class="mt-1 w-full border border-[#e3e1da] rounded-lg px-3 py-2 text-sm bg-paper focus:bg-white outline-none"></textarea>
</div>
@foreach($modules as $mod)
<div class="bg-white border border-border rounded-xl p-3">
<div class="flex items-center gap-2"><span class="text-sm font-bold">{{ $mod['label'] }}</span><span class="text-[10px] bg-[#f3f1ee] px-2 py-0.5 rounded-full">{{ collect($mod['actions'])->filter(fn($a)=>!empty($editPerms[$mod['id']][$a]))->count() }} / 5</span>
<button type="button" class="ml-auto text-xs text-blue font-bold" wire:click="$set('editPerms.{{ $mod['id'] }}', ['view'=>true,'create'=>true,'edit'=>true,'publish'=>true,'delete'=>true])">Allow all</button>
</div>
<div class="flex flex-wrap gap-1.5 mt-2">
@foreach($mod['actions'] as $act)
<label class="text-xs px-3 py-1.5 rounded-full border cursor-pointer select-none {{ !empty($editPerms[$mod['id']][$act]) ? 'bg-blue-bg border-blue text-blue font-bold' : 'bg-white border-[#e3e1da] text-[#4b4e5c] hover:border-navy-800' }}">
<input type="checkbox" class="sr-only" wire:model.live="editPerms.{{ $mod['id'] }}.{{ $act }}">
{{ ucfirst($act) }}
</label>
@endforeach
</div>
</div>
@endforeach
</div>
<div class="sticky bottom-0 bg-panel border-t border-border p-4 flex gap-2 justify-end">
<x-btn variant="outline" wire:click="closeEdit">Discard</x-btn>
<x-btn variant="primary" wire:click="saveEdit">Save role</x-btn>
</div>
</div>
</div>
@endif

{{-- New role modal --}}
@if($showNewModal)
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
<div class="absolute inset-0 bg-navy-900/50 backdrop-blur-sm" wire:click="$set('showNewModal', false)"></div>
<div class="relative bg-white rounded-2xl w-[min(520px,100%)] shadow-2xl overflow-hidden">
<div class="flex items-center gap-2 px-5 py-4 border-b border-border"><span class="font-serif text-lg font-bold">Create a role</span><button wire:click="$set('showNewModal', false)" class="ml-auto w-7 h-7 rounded-lg border">✕</button></div>
<div class="p-5 space-y-3">
<div><label class="text-xs font-semibold">Role name *</label><input wire:model="newName" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. Night Desk Editor"></div>
<div><label class="text-xs font-semibold">Description</label><textarea wire:model="newDesc" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" placeholder="What is this role for?"></textarea></div>
<div><label class="text-xs font-semibold">Start from</label><select wire:model="newCopyFrom" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"><option value="">Blank (no permissions)</option>@foreach($roles as $r)<option value="{{ $r->id }}">Copy from {{ $r->name }}</option>@endforeach</select></div>
</div>
<div class="flex gap-2 justify-end p-4 border-t border-border"><x-btn variant="outline" wire:click="$set('showNewModal', false)">Cancel</x-btn><x-btn variant="primary" wire:click="createRole">Create role</x-btn></div>
</div>
</div>
@endif

{{-- Delete confirm --}}
@if($deleteId)
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
<div class="absolute inset-0 bg-navy-900/50" wire:click="$set('deleteId', null)"></div>
<div class="relative bg-white rounded-2xl w-[min(420px,100%)] p-5 shadow-2xl">
<h3 class="font-serif font-bold">Delete role?</h3><p class="text-sm text-muted mt-2">System roles and roles with members cannot be deleted. Reassign members first.</p>
<div class="flex gap-2 justify-end mt-4"><x-btn variant="outline" wire:click="$set('deleteId', null)">Cancel</x-btn><x-btn variant="primary" wire:click="deleteRole">Delete</x-btn></div>
</div>
</div>
@endif

{{-- Invite modal --}}
@if($showInviteModal)
<div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
<div class="absolute inset-0 bg-navy-900/50" wire:click="$set('showInviteModal', false)"></div>
<div class="relative bg-white rounded-2xl w-[min(520px,100%)] shadow-2xl overflow-hidden">
<div class="flex items-center gap-2 px-5 py-4 border-b"><span class="font-serif font-bold">Invite a team member</span><button wire:click="$set('showInviteModal', false)" class="ml-auto w-7 h-7 border rounded-lg">✕</button></div>
<div class="p-5 space-y-3">
<div><label class="text-xs font-semibold">Full name *</label><input wire:model="invName" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div><label class="text-xs font-semibold">Work email *</label><input wire:model="invEmail" type="email" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
<div><label class="text-xs font-semibold">Desk</label><select wire:model="invDesk" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option>English desk</option><option>Bangla desk</option><option>Photo desk</option><option>Business</option><option>Management</option></select></div>
<div><label class="text-xs font-semibold">Role</label><select wire:model="invRole" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">@foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
</div>
<div class="flex justify-end gap-2 p-4 border-t"><x-btn variant="outline" wire:click="$set('showInviteModal', false)">Cancel</x-btn><x-btn variant="primary" wire:click="invite">Send invite</x-btn></div>
</div>
</div>
@endif

<x-toast />
</div>
