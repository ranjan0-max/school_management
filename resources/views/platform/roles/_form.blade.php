@php
    $isEditing = $role->exists;
    $oldMenus = old('menus');
    $checked = function (int $menuId, string $action) use ($oldMenus, $grants): bool {
        if (is_array($oldMenus)) {
            return filter_var($oldMenus[$menuId][$action] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $grants[$menuId][$action] ?? false;
    };
    $actions = \App\Enums\MenuAction::cases();
@endphp

<div class="row g-4">
    <div class="col-xl-4">
        <section class="dashboard-panel role-details-panel">
            <div class="account-section-heading">
                <span class="account-section-icon">R</span>
                <div>
                    <h2>Role details</h2>
                    <p>{{ $role->isPlatform() ? 'Platform role · all schools' : 'School role · '.($school?->name ?? '') }}</p>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label app-form-label" for="name">Role name <span class="text-danger">*</span></label>
                <input id="name" class="form-control app-form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $role->name) }}" maxlength="255" placeholder="{{ $role->isPlatform() ? 'Principal' : 'Teacher' }}" required autofocus>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label app-form-label" for="description">Description</label>
                <textarea id="description" class="form-control app-form-control school-address @error('description') is-invalid @enderror" name="description" maxlength="2000" rows="3">{{ old('description', $role->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <input name="all_school_menus" type="hidden" value="0">
            <label class="role-active-choice mb-3" for="all_school_menus">
                <input id="all_school_menus" class="form-check-input" name="all_school_menus" type="checkbox" value="1" @checked((bool) old('all_school_menus', $role->all_school_menus))>
                <span><strong>All school menus</strong><small>Every action on every menu the school has, including menus added later. The table on the right is ignored while this is on.</small></span>
            </label>

            <input name="is_active" type="hidden" value="0">
            <label class="role-active-choice" for="is_active">
                <input id="is_active" class="form-check-input" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $role->is_active ?? true))>
                <span><strong>Active role</strong><small>Inactive roles give no access to anyone holding them.</small></span>
            </label>
        </section>
    </div>

    <div class="col-xl-8">
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading">
                <div><span class="section-kicker">MENU ACCESS</span><h2>What this role can do</h2></div>
                <span class="dashboard-panel-badge">{{ $role->isPlatform() ? 'Limited per school' : 'School boundary enforced' }}</span>
            </div>
            <p class="role-permission-intro">
                {{ $role->isPlatform()
                    ? 'All menus are listed. In each school only the menus assigned to that school take effect.'
                    : 'Only menus assigned to '.($school?->name ?? 'this school').' are listed.' }}
                A dash means the action does not apply to that page.
            </p>

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
                    @if ($school)
                        <p>Assign menus to {{ $school->name }} before configuring its roles.</p>
                        <a class="btn school-access-button" href="{{ route('platform.schools.access', $school) }}">Assign school menus</a>
                    @else
                        <p>Run the navigation seeder to load the menu catalog.</p>
                    @endif
                </div>
            @endforelse
        </section>
    </div>
</div>

<div class="school-form-actions">
    <a class="btn school-secondary-button" href="{{ route('platform.roles.index') }}">Cancel</a>
    <button class="btn btn-primary app-btn-primary" type="submit">{{ $isEditing ? 'Save role' : 'Create role' }}</button>
</div>
