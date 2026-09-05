<div>
    <!-- Topbar & Breadcrumb -->
    <div class="breadcrumb">
        <a href="{{ route('dashboard') }}">Home</a> &nbsp;/&nbsp; Settings &nbsp;/&nbsp; Roles &amp; access
    </div>

    <div class="topbar">
        <h1>Roles &amp; access</h1>
        <div class="topbar-actions">
            <button type="button" class="btn btn-outline" wire:click="openInviteModal">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                Invite member
            </button>
            <button type="button" class="btn btn-primary" wire:click="openNewRole">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New role
            </button>
        </div>
    </div>

    <!-- 4-Stat Metric Strip -->
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-num" id="stRoles">{{ $totalRoles }}</div>
            <div class="stat-label">Total roles</div>
        </div>
        <div class="stat-card">
            <div class="stat-num green" id="stPeople">{{ $totalPeople }}</div>
            <div class="stat-label">People with access</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" id="stCustom">{{ $customRoles }}</div>
            <div class="stat-label">Custom roles</div>
        </div>
        <div class="stat-card">
            <div class="stat-num amber" id="stInvited">{{ $pendingInvites }}</div>
            <div class="stat-label">Pending invites</div>
        </div>
    </div>

    <!-- Page Tabs -->
    <div class="pg-tabs">
        <button type="button" class="pg-tab {{ $activeTab === 'roles' ? 'active' : '' }}" wire:click="setTab('roles')">
            Roles <span class="cnt">{{ $totalRoles }}</span>
        </button>
        <button type="button" class="pg-tab {{ $activeTab === 'people' ? 'active' : '' }}" wire:click="setTab('people')">
            People <span class="cnt">{{ $people->count() }}</span>
        </button>
        <button type="button" class="pg-tab {{ $activeTab === 'audit' ? 'active' : '' }}" wire:click="setTab('audit')">
            Activity <span class="cnt">{{ $audits->count() }}</span>
        </button>
    </div>

    <!-- ================= ROLES PANEL ================= -->
    <section class="pg-panel {{ $activeTab === 'roles' ? 'active' : '' }}">
        <div class="role-grid" id="roleGrid">
            @foreach($roles as $role)
                @php
                    $roleGrad = $this->getRoleGradient($role);
                    $mem = $role->users;
                @endphp
                <div class="role-card">
                    <div class="rc-top">
                        <div class="rc-logo {{ $roleGrad }}">{{ $this->getInitials($role->name) }}</div>
                        <div>
                            <div class="rc-name">
                                {{ $role->name }}
                                @if($role->is_locked)
                                    <span class="rc-lock">System</span>
                                @elseif($role->type === 'client')
                                    <span class="rc-lock" style="background:var(--blue, #3b6fe0)">Client</span>
                                @else
                                    <span class="rc-custom">Custom</span>
                                @endif
                            </div>
                            <div class="rc-desc">{{ $role->description }}</div>
                        </div>
                    </div>

                    <div class="perm-chips">
                        @php $hasAnyPerm = false; @endphp
                        @foreach($modules as $m)
                            @php
                                $p = $role->permissions->firstWhere('module', $m['id']);
                                $count = 0;
                                if ($p) {
                                    foreach ($m['actions'] as $a) {
                                        if (!empty($p->{'can_'.$a})) $count++;
                                    }
                                }
                            @endphp
                            @if($count > 0)
                                @php $hasAnyPerm = true; @endphp
                                @if($count === count($m['actions']))
                                    <span class="perm-chip full">{{ $m['short'] }}</span>
                                @elseif($count === 1 && !empty($p->can_view))
                                    <span class="perm-chip view">{{ $m['short'] }}</span>
                                @else
                                    <span class="perm-chip">{{ $m['short'] }} {{ $count }}/{{ count($m['actions']) }}</span>
                                @endif
                            @endif
                        @endforeach
                        @if(! $hasAnyPerm)
                            <span class="perm-chip view">No module access</span>
                        @endif
                    </div>

                    <div class="rc-members">
                        @foreach($mem->take(4) as $idx => $u)
                            <span class="rc-av {{ $this->getUserGradient($idx) }}" title="{{ $u->name }}">{{ $this->getInitials($u->name) }}</span>
                        @endforeach
                        @if($mem->count() > 4)
                            <span class="rc-av more">+{{ $mem->count() - 4 }}</span>
                        @endif
                        <span class="rc-mem-txt">{{ $mem->count() ? $mem->count() . ' member' . ($mem->count() > 1 ? 's' : '') : 'No members yet' }}</span>
                    </div>

                    <div class="rc-foot">
                        <button type="button" class="rc-btn" wire:click="openDrawer({{ $role->id }})">
                            {{ $role->is_locked ? 'View permissions' : 'Edit permissions' }}
                        </button>
                        <button type="button" class="rc-btn" wire:click="duplicateRole({{ $role->id }})">
                            Duplicate
                        </button>
                        @if(! $role->is_locked)
                            <button type="button" class="rc-btn danger" wire:click="openDelete({{ $role->id }})">
                                Delete
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach

            <!-- Create a custom role dashed card -->
            <button type="button" class="role-new" id="roleNewCard" wire:click="openNewRole">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Create a custom role
            </button>
        </div>

        <div class="info-banner">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <div>
                <strong>Client roles</strong> (like <code>Client Bangla (Without AP)</code>) are portal logins for subscribers — they control what a client sees on the <a href="{{ url('/portal') }}">client portal</a>, not the admin. Staff roles control this newsroom panel. A person needs at least the <strong>View</strong> permission on a module to see it in their sidebar.
            </div>
        </div>
    </section>

    <!-- ================= PEOPLE PANEL ================= -->
    <section class="pg-panel {{ $activeTab === 'people' ? 'active' : '' }}">
        <div class="pp-list" id="ppList">
            <div class="pp-row head">
                <span></span>
                <span>Member</span>
                <span class="pp-hide-m">Desk</span>
                <span>Role</span>
                <span class="pp-hide-m">Status</span>
                <span></span>
            </div>
            @foreach($people as $idx => $p)
                @php $isYou = (auth()->id() === $p->id); @endphp
                <div class="pp-row">
                    <div class="pp-av {{ $this->getUserGradient($idx) }}">{{ $this->getInitials($p->name) }}</div>
                    <div>
                        <div class="pp-name">
                            {{ $p->name }}
                            @if($isYou)
                                <span class="pp-you">You</span>
                            @endif
                        </div>
                        <div class="pp-email">{{ $p->email }}</div>
                    </div>
                    <div class="pp-desk pp-hide-m">{{ $p->desk ?? '—' }}</div>
                    <div>
                        <select class="select-plain pp-role-sel"
                                wire:change="updateUserRole({{ $p->id }}, $event.target.value)"
                                {{ $isYou ? 'disabled title="You cannot change your own role"' : '' }}>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" {{ $p->role_id === $r->id ? 'selected' : '' }}>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pp-hide-m">
                        <span class="pp-status {{ $p->status }}">
                            <i></i>{{ $p->status }}
                        </span>
                        <div class="pp-last">
                            @if($p->status === 'invited')
                                Invited
                            @elseif($p->last_seen_at)
                                {{ $p->last_seen_at->diffForHumans() }}
                            @else
                                Online now
                            @endif
                        </div>
                    </div>
                    <div>
                        @if(! $isYou)
                            @if($p->status === 'invited')
                                <button type="button" class="pp-act" wire:click="resendInvite({{ $p->id }})">Resend invite</button>
                            @elseif($p->status === 'deactivated')
                                <button type="button" class="pp-act" wire:click="activateUser({{ $p->id }})">Reactivate</button>
                            @else
                                <button type="button" class="pp-act danger" wire:click="deactivateUser({{ $p->id }})">Deactivate</button>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- ================= ACTIVITY PANEL ================= -->
    <section class="pg-panel {{ $activeTab === 'audit' ? 'active' : '' }}">
        <div class="au-list" id="auList">
            @forelse($audits as $a)
                @php
                    $diff = is_string($a->diff) ? json_decode($a->diff, true) : (array) $a->diff;
                    $color = $diff['color'] ?? 'var(--blue, #3b6fe0)';
                @endphp
                <div class="au-row">
                    <span class="au-dot" style="background: {{ $color }}"></span>
                    <div>
                        @if(!empty($diff['message']))
                            <div class="au-text">{!! $diff['message'] !!}</div>
                        @else
                            <div class="au-text">
                                <b>{{ $a->actor_type }}</b> {{ $a->action }}
                                @if($a->entity_type)
                                    <b>{{ $a->entity_type }} #{{ $a->entity_id }}</b>
                                @endif
                            </div>
                        @endif
                        <div class="au-time">{{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div class="p-4 text-sm text-muted">No activity logs recorded yet.</div>
            @endforelse
        </div>
    </section>

    <!-- ================= ROLE EDITOR DRAWER ================= -->
    <div class="drawer-overlay {{ $editingRoleId ? 'open' : '' }}" wire:click="closeDrawer"></div>
    <aside class="drawer {{ $editingRoleId ? 'open' : '' }}">
        @if($editingRoleId)
            <div class="dr-head">
                <div class="dr-top">
                    <div class="dr-logo {{ $editGrad }}">{{ $this->getInitials($editName ?: 'Role') }}</div>
                    <div>
                        <div class="dr-name">{{ $editName ?: 'Role' }}</div>
                        <div class="dr-sub">
                            {{ $editLocked ? 'System role' : ($editRoleType === 'client' ? 'Client role' : 'Custom role') }}
                            · {{ $editingRole ? $editingRole->users->count() : 0 }} member{{ ($editingRole && $editingRole->users->count() === 1) ? '' : 's' }}
                        </div>
                    </div>
                    <button type="button" class="dr-close" wire:click="closeDrawer" title="Close">✕</button>
                </div>
            </div>
            <div class="dr-body">
                @if(! $editLocked)
                    <div class="dr-sec">
                        <div class="dr-sec-title">Role info</div>
                        <div class="field">
                            <label class="field-label">Name</label>
                            <input type="text" class="text-input" wire:model.live="editName">
                            @error('editName') <span class="text-xs text-crimson-dark mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Description</label>
                            <textarea class="text-area" wire:model.live="editDesc"></textarea>
                            @error('editDesc') <span class="text-xs text-crimson-dark mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="field" style="margin-bottom:0">
                            <label class="field-label">Colour</label>
                            <div class="color-dots">
                                @foreach(['g1','g2','g3','g4','g5','g6','g7','g8'] as $g)
                                    <span class="color-dot {{ $g }} {{ $editGrad === $g ? 'sel' : '' }}"
                                          wire:click="setEditGrad('{{ $g }}')"></span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="dr-sec">
                        <div class="dr-sec-title">Quick presets</div>
                        <div class="preset-row">
                            <button type="button" class="preset-btn" wire:click="applyPreset('view')">View only</button>
                            <button type="button" class="preset-btn" wire:click="applyPreset('uploader')">Uploader</button>
                            <button type="button" class="preset-btn" wire:click="applyPreset('editor')">Editor</button>
                            <button type="button" class="preset-btn" wire:click="applyPreset('full')">Full access</button>
                            <button type="button" class="preset-btn" wire:click="applyPreset('clear')">Clear all</button>
                        </div>
                    </div>
                @else
                    <div class="mx-locked" style="margin-bottom:14px">
                        <b>System role.</b> Admin permissions are fixed so the panel can never lock itself out. Create a custom role if you need a restricted variant.
                    </div>
                @endif

                <!-- Permissions Section -->
                <div class="dr-sec">
                    <div class="dr-sec-title">Permissions{{ $editLocked ? ' — read only' : '' }}</div>
                    @foreach($modules as $m)
                        @php
                            $mid = $m['id'];
                            $onCount = 0;
                            foreach ($m['actions'] as $a) {
                                if (!empty($editPerms[$mid][$a])) $onCount++;
                            }
                            $isAll = ($onCount === count($m['actions']));
                        @endphp
                        <div class="mx-mod">
                            <div class="mx-mod-head">
                                <span class="mx-mod-name">{{ $m['label'] }}</span>
                                <span class="mx-mod-count {{ $onCount > 0 ? 'on' : '' }}">{{ $onCount }}/{{ count($m['actions']) }}</span>
                                @if(! $editLocked)
                                    <button type="button" class="mx-all" wire:click="toggleModuleAll('{{ $mid }}')">
                                        {{ $isAll ? 'None' : 'All' }}
                                    </button>
                                @endif
                            </div>
                            <div class="mx-chips">
                                @foreach($m['actions'] as $act)
                                    <label class="mx-chip {{ in_array($act, $dangerActions, true) ? 'danger' : '' }}"
                                           style="{{ $editLocked ? 'pointer-events:none;opacity:0.75' : '' }}">
                                        <input type="checkbox"
                                               wire:model.live="editPerms.{{ $mid }}.{{ $act }}"
                                               {{ $editLocked ? 'disabled' : '' }}>
                                        <span>{{ ucfirst($act) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Members Section -->
                <div class="dr-sec">
                    <div class="dr-sec-title">Members ({{ $editingRole ? $editingRole->users->count() : 0 }})</div>
                    @if($editingRole && $editingRole->users->count() > 0)
                        <div class="mx-members">
                            @foreach($editingRole->users as $uIdx => $u)
                                <span class="mx-mem">
                                    <i class="{{ $this->getUserGradient($uIdx) }}">{{ $this->getInitials($u->name) }}</i>
                                    {{ $u->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div class="field-hint">Nobody has this role yet — assign it from the People tab.</div>
                    @endif
                </div>
            </div>

            <div class="dr-foot">
                <span class="dr-foot-note">Changes apply to every member of this role immediately</span>
                <span class="spacer"></span>
                <button type="button" class="btn btn-outline btn-sm" wire:click="closeDrawer">Discard</button>
                @if(! $editLocked)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="saveRole">Save role</button>
                @endif
            </div>
        @endif
    </aside>

    <!-- ================= CREATE ROLE MODAL ================= -->
    <div class="modal-overlay {{ $showNewModal ? 'open' : '' }}" wire:click.self="closeNewModal">
        <div class="modal">
            <div class="mo-head">
                <div class="mo-title">Create a role</div>
                <button type="button" class="mo-close" wire:click="closeNewModal" title="Close">✕</button>
            </div>
            <div class="mo-body">
                <div class="field">
                    <label class="field-label">Role name <span class="req">*</span></label>
                    <input type="text" class="text-input" wire:model.live="newName" placeholder="e.g. Night Desk Editor">
                    @error('newName') <span class="text-xs text-crimson-dark mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Description</label>
                    <textarea class="text-area" wire:model.live="newDesc" placeholder="What is this role for?"></textarea>
                    @error('newDesc') <span class="text-xs text-crimson-dark mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Colour</label>
                    <div class="color-dots">
                        @foreach(['g1','g2','g3','g4','g5','g6','g7','g8'] as $g)
                            <span class="color-dot {{ $g }} {{ $newGrad === $g ? 'sel' : '' }}"
                                  wire:click="setNewGrad('{{ $g }}')"></span>
                        @endforeach
                    </div>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label class="field-label">Start from</label>
                    <select class="select-plain" wire:model="newCopyFrom">
                        <option value="">Blank — no permissions</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}">Copy of {{ $r->name }}</option>
                        @endforeach
                    </select>
                    <div class="field-hint">Copies that role's permissions — you can fine-tune right after creating</div>
                </div>
            </div>
            <div class="mo-actions">
                <button type="button" class="btn btn-outline" wire:click="closeNewModal">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="createRole">Create role</button>
            </div>
        </div>
    </div>

    <!-- ================= DELETE ROLE MODAL ================= -->
    <div class="modal-overlay {{ $showDeleteModal ? 'open' : '' }}" wire:click.self="closeDeleteModal">
        <div class="modal">
            <div class="mo-head">
                <div class="mo-title">Delete "{{ $deleteRoleName }}"?</div>
                <button type="button" class="mo-close" wire:click="closeDeleteModal" title="Close">✕</button>
            </div>
            <div class="mo-body">
                @if($deleteRoleMemberCount > 0)
                    <div class="warn-box">
                        <span>⚠</span>
                        <div>
                            <b>{{ $deleteRoleMemberCount }} member{{ $deleteRoleMemberCount > 1 ? 's' : '' }}</b> still {{ $deleteRoleMemberCount > 1 ? 'have' : 'has' }} this role: {{ $deleteRoleMemberNames }}.<br>
                            Reassign them from the People tab first — then you can delete it.
                        </div>
                    </div>
                @else
                    <p style="font-size: 13.5px; color: var(--ink, #1c1f2e); line-height: 1.6;">
                        This removes the role permanently. People are not affected because nobody has it. Audit entries stay in the activity log.
                    </p>
                @endif
            </div>
            <div class="mo-actions">
                <button type="button" class="btn btn-outline" wire:click="closeDeleteModal">Cancel</button>
                @if($deleteRoleMemberCount > 0)
                    <button type="button" class="btn btn-primary" style="background:var(--crimson, #e5484d); opacity:0.5; cursor:not-allowed;" disabled>
                        Delete role
                    </button>
                @else
                    <button type="button" class="btn btn-primary" style="background:var(--crimson, #e5484d);" wire:click="deleteRole">
                        Delete role
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- ================= INVITE MEMBER MODAL ================= -->
    <div class="modal-overlay {{ $showInviteModal ? 'open' : '' }}" wire:click.self="closeInviteModal">
        <div class="modal">
            <div class="mo-head">
                <div class="mo-title">Invite a team member</div>
                <button type="button" class="mo-close" wire:click="closeInviteModal" title="Close">✕</button>
            </div>
            <div class="mo-body">
                <div class="field">
                    <label class="field-label">Full name <span class="req">*</span></label>
                    <input type="text" class="text-input" wire:model.live="invName" placeholder="e.g. Farhana Yeasmin">
                    @error('invName') <span class="text-xs text-crimson-dark mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Work email <span class="req">*</span></label>
                    <input type="email" class="text-input" wire:model.live="invEmail" placeholder="name@unbnews.org">
                    @error('invEmail') <span class="text-xs text-crimson-dark mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Desk</label>
                    <select class="select-plain" wire:model="invDesk">
                        <option value="English desk">English desk</option>
                        <option value="Bangla desk">Bangla desk</option>
                        <option value="Photo desk">Photo desk</option>
                        <option value="Business">Business</option>
                        <option value="Management">Management</option>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label class="field-label">Role</label>
                    <select class="select-plain" wire:model="invRole">
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <div class="field-hint">They get an email with a sign-in link — access starts only after they accept</div>
                </div>
            </div>
            <div class="mo-actions">
                <button type="button" class="btn btn-outline" wire:click="closeInviteModal">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="inviteMember">Send invite</button>
            </div>
        </div>
    </div>
</div>
