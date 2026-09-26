{{--
    Per-user Allow / Deny overrides on top of the role.
    $groups: the user's school catalog, $overrides: saved overrides, $roleGrants: what the role gives
    $disabled (optional): read-only
    $emptyMessage, $assignMenusUrl (optional): shown when there are no pages
--}}
@php
    $actions = \App\Enums\MenuAction::cases();
    $oldOverrides = old('overrides');
    $currentOverrides = is_array($oldOverrides) ? $oldOverrides : $overrides;
    $disabled = $disabled ?? false;
@endphp

@forelse ($groups as $group)
    <div class="role-permission-module">
        <div class="role-permission-module-heading">
            <span>{{ strtoupper(substr($group->name, 0, 1)) }}</span>
            <div><strong>{{ $group->name }}</strong><small>{{ $group->children->count() }} pages</small></div>
        </div>
        <div class="table-responsive">
            <table class="table access-matrix mb-0">
                <thead>
                    <tr>
                        <th scope="col">Page</th>
                        @foreach ($actions as $action)
                            <th scope="col" class="text-center">{{ $action->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group->children as $menu)
                        @php($available = array_map(fn ($a) => $a->value, $menu->availableActions()))
                        <tr>
                            <th scope="row">{{ $menu->name }}</th>
                            @foreach ($actions as $action)
                                <td class="text-center">
                                    @if (in_array($action->value, $available, true))
                                        @php($value = $currentOverrides[$menu->id][$action->value] ?? '')
                                        @php($fromRole = $roleGrants[$menu->id][$action->value] ?? false)
                                        <div class="access-override-cell">
                                            <select class="form-select access-override-select {{ $value !== '' ? 'is-set is-'.$value : '' }}" name="overrides[{{ $menu->id }}][{{ $action->value }}]" aria-label="{{ $action->label() }} {{ $menu->name }}" @disabled($disabled)>
                                                <option value="" @selected($value === '')>Role</option>
                                                <option value="allow" @selected($value === 'allow')>Allow</option>
                                                <option value="deny" @selected($value === 'deny')>Deny</option>
                                            </select>
                                            <small class="access-role-hint {{ $fromRole ? 'yes' : 'no' }}" title="Role {{ $fromRole ? 'allows' : 'does not allow' }}">{{ $fromRole ? '✓' : '✕' }}</small>
                                        </div>
                                    @else
                                        <span class="access-matrix-na" aria-hidden="true">&ndash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="role-permission-empty">
        <strong>No menus available</strong>
        <p>{{ $emptyMessage }}</p>
        @if ($assignMenusUrl ?? null)
            <a class="btn school-access-button" href="{{ $assignMenusUrl }}">Assign school menus</a>
        @endif
    </div>
@endforelse
