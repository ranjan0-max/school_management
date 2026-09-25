@extends('layouts.dashboard', ['title' => 'Edit User', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    @php
        $actions = \App\Enums\MenuAction::cases();
        $oldOverrides = old('overrides');
        $currentOverrides = is_array($oldOverrides) ? $oldOverrides : $overrides;
    @endphp

    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">{{ strtoupper($user->school?->name ?? 'USER') }}</span>
            <h1>{{ $user->name ?: $user->email }}</h1>
            <p>{{ $user->email }} &middot; {{ $user->role ? 'Role: '.$user->role->name : 'No role assigned' }}</p>
        </div>
        <a class="btn school-secondary-button" href="{{ route('platform.users.index', ['school_id' => $user->school_id]) }}">Back to users</a>
    </div>

    @if (session('status'))
        <div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Changes were not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="mt-4" method="POST" action="{{ route('platform.users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('platform.users._form')

        <section class="dashboard-panel mt-4">
            <div class="dashboard-panel-heading">
                <div><span class="section-kicker">EXTRA ACCESS</span><h2>Individual menu access</h2></div>
                <span class="dashboard-panel-badge">Deny always wins</span>
            </div>
            <p class="role-permission-intro">
                Leave cells on <strong>Role</strong> for normal access. Choose <strong>Allow</strong> to give this user something the role does not,
                or <strong>Deny</strong> to take something away. The small tick or cross shows what the role gives today.
            </p>

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
                                                        <select class="form-select access-override-select {{ $value !== '' ? 'is-set is-'.$value : '' }}" name="overrides[{{ $menu->id }}][{{ $action->value }}]" aria-label="{{ $action->label() }} {{ $menu->name }}">
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
                    @if ($user->school)
                        <p>{{ $user->school->name }} has no menus yet.</p>
                        <a class="btn school-access-button" href="{{ route('platform.schools.access', $user->school) }}">Assign school menus</a>
                    @endif
                </div>
            @endforelse
        </section>

        <div class="school-form-actions">
            <a class="btn school-secondary-button" href="{{ route('platform.users.index') }}">Cancel</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Save user</button>
        </div>
    </form>
@endsection
