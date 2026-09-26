<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Super Admin sets the order of menu groups and of the pages inside each group.
 * The order is the same for every school: sidebar, school access and role screens.
 */
class MenuOrderController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('platform.menus.order', [
            'groups' => Menu::query()
                ->whereNull('parent_id')
                ->with('children')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * Expects every group once (groups[]) and, per group, every page once (pages[group][]).
     * Pages stay in their own group; only the order changes.
     */
    public function update(Request $request): RedirectResponse
    {
        $groups = Menu::query()->whereNull('parent_id')->with('children')->get()->keyBy('id');
        $groupOrder = $this->ids($request->input('groups'));
        $pageInput = $request->input('pages');
        $pageInput = is_array($pageInput) ? $pageInput : [];

        if (! $this->sameIds($groupOrder, $groups->keys()->all())) {
            throw ValidationException::withMessages(['groups' => 'The menu list has changed. Reload the page and try again.']);
        }

        $pageOrders = [];

        foreach ($groups as $groupId => $group) {
            $order = $this->ids($pageInput[$groupId] ?? []);

            if (! $this->sameIds($order, $group->children->modelKeys())) {
                throw ValidationException::withMessages(['groups' => 'The menu list has changed. Reload the page and try again.']);
            }

            $pageOrders[$groupId] = $order;
        }

        DB::transaction(function () use ($groupOrder, $pageOrders): void {
            foreach ($groupOrder as $position => $groupId) {
                Menu::query()->whereKey($groupId)->update(['sort_order' => ($position + 1) * 10]);

                foreach ($pageOrders[$groupId] as $pagePosition => $pageId) {
                    Menu::query()->whereKey($pageId)->update(['sort_order' => ($pagePosition + 1) * 10]);
                }
            }
        });

        $this->audit->record(
            event: 'menus.reordered',
            actor: $request->user(),
            newValues: ['groups' => $groupOrder, 'pages' => $pageOrders],
        );

        return redirect()->route('platform.menus.order')->with('status', 'Menu order saved. Every school now sees menus in this order.');
    }

    /**
     * @return array<int, int>
     */
    private function ids(mixed $values): array
    {
        return is_array($values) ? array_values(array_map('intval', array_filter($values, 'is_scalar'))) : [];
    }

    /**
     * Same ids, each exactly once, in any order.
     *
     * @param  array<int, int>  $given
     * @param  array<int, int|string>  $expected
     */
    private function sameIds(array $given, array $expected): bool
    {
        $expected = array_map('intval', $expected);
        sort($expected);
        $sorted = $given;
        sort($sorted);

        return $sorted === $expected;
    }
}
