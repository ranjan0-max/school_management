{{--
    Tick boxes for what a role can do, one table per menu group.
    $groups: catalog groups with ->children pages
    $grants: menu id => action => bool (the saved ticks)
    $emptyMessage, $assignMenusUrl (optional): shown when there are no pages
--}}
@php
    $oldMenus = old('menus');
    $checked = function (int $menuId, string $action) use ($oldMenus, $grants): bool {
        if (is_array($oldMenus)) {
            return filter_var($oldMenus[$menuId][$action] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $grants[$menuId][$action] ?? false;
    };
    $actions = \App\Enums\MenuAction::cases();
@endphp

@forelse ($groups as $group)
    <div class="role-permission-module" data-check-scope>
        <div class="role-permission-module-heading">
            <span>{{ strtoupper(substr($group->name, 0, 1)) }}</span>
            <div><strong>{{ $group->name }}</strong><small>{{ $group->children->count() }} pages</small></div>
            <label class="check-all-toggle"><input class="form-check-input" type="checkbox" data-check-all><span>Select all</span></label>
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
                                        <input class="form-check-input" type="checkbox" name="menus[{{ $menu->id }}][{{ $action->value }}]" value="1" aria-label="{{ $action->label() }} {{ $menu->name }}" @checked($checked($menu->id, $action->value))>
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
