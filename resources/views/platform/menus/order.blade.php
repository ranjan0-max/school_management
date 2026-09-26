@extends('layouts.dashboard', ['title' => 'Menu Order', 'panelLabel' => 'SUPER ADMIN', 'workspaceName' => 'Platform Control'])

@section('content')
    <div class="dashboard-page-heading">
        <div>
            <span class="section-kicker">PLATFORM</span>
            <h1>Menu order</h1>
            <p>Set the order of menu groups and of the pages inside each group. Every school sees this order in its sidebar, access and role screens.</p>
        </div>
    </div>

    @if (session('status'))<div class="alert account-alert-success mt-4 mb-0" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert school-access-error mt-4 mb-0" role="alert"><strong>Order was not saved.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form class="mt-4" method="POST" action="{{ route('platform.menus.order.update') }}" data-menu-order>
        @csrf
        @method('PUT')

        <div class="menu-order-list" data-sortable-list>
            @foreach ($groups as $group)
                <section class="dashboard-panel menu-order-group" data-sortable-item>
                    <input type="hidden" name="groups[]" value="{{ $group->id }}">

                    <div class="menu-order-row menu-order-group-row">
                        <span class="menu-order-letter" aria-hidden="true">{{ strtoupper(mb_substr($group->name, 0, 1)) }}</span>
                        <strong>{{ $group->name }}</strong>
                        <small>{{ $group->children->count() }} page(s){{ $group->is_active ? '' : ' · inactive' }}</small>
                        @include('platform.menus._move-buttons', ['label' => $group->name])
                    </div>

                    <ol class="menu-order-pages" data-sortable-list>
                        @foreach ($group->children as $page)
                            <li class="menu-order-row" data-sortable-item>
                                <input type="hidden" name="pages[{{ $group->id }}][]" value="{{ $page->id }}">
                                <span class="dashboard-nav-icon" aria-hidden="true"><x-sidebar-icon :name="$page->key" /></span>
                                <span class="menu-order-name">{{ $page->name }}</span>
                                <small>{{ $page->route_name ? '' : 'Coming soon' }}{{ $page->is_active ? '' : ' · inactive' }}</small>
                                @include('platform.menus._move-buttons', ['label' => $page->name])
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>

        <div class="school-form-actions">
            <span class="text-muted small me-auto">Use ▲ ▼ to move a group or a page, then save. Pages stay in their own group.</span>
            <a class="btn school-secondary-button" href="{{ route('platform.menus.order') }}">Reset</a>
            <button class="btn btn-primary app-btn-primary" type="submit">Save order</button>
        </div>
    </form>
@endsection
