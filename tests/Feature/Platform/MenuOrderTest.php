<?php

namespace Tests\Feature\Platform;

use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MenuOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
    }

    public function test_super_admin_reorders_groups_and_pages_for_every_school(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$groups, $pages] = $this->currentOrder();

        // Attendance first; inside Academic Management, Timetable first.
        $attendance = $this->id('attendance');
        $groups = [$attendance, ...array_values(array_diff($groups, [$attendance]))];
        $academic = $this->id('academic_management');
        $timetable = $this->id('timetable');
        $pages[$academic] = [$timetable, ...array_values(array_diff($pages[$academic], [$timetable]))];

        $this->save($admin, $groups, $pages)->assertRedirect(route('platform.menus.order'))->assertSessionHasNoErrors();

        $this->assertSame($attendance, (int) Menu::query()->whereNull('parent_id')->orderBy('sort_order')->value('id'));
        $this->assertSame($timetable, (int) Menu::query()->where('parent_id', $academic)->orderBy('sort_order')->value('id'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'menus.reordered']);

        $school = School::factory()->create();
        $school->menus()->attach(Menu::query()->whereIn('key', ['classes', 'timetable', 'student_attendance'])->pluck('id'));
        $user = User::factory()->create([
            'school_id' => $school->getKey(),
            'role_id' => Role::factory()->platform()->allSchoolMenus()->create()->getKey(),
        ]);

        $html = $this->actingAs($user)->get(route('school.dashboard'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '>Academic Management<'), strpos($html, '>Attendance<'));
        $this->assertLessThan(strpos($html, route('school.classes.index')), strpos($html, route('school.timetable.index')));

        // Re-seeding keeps the Super Admin's order.
        $this->seed(NavigationSeeder::class);
        $this->assertSame($attendance, (int) Menu::query()->whereNull('parent_id')->orderBy('sort_order')->value('id'));
        $this->assertSame($timetable, (int) Menu::query()->where('parent_id', $academic)->orderBy('sort_order')->value('id'));
    }

    public function test_tampered_or_incomplete_orders_are_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$groups, $pages] = $this->currentOrder();
        $before = Menu::query()->orderBy('id')->pluck('sort_order', 'id')->all();

        $this->save($admin, array_slice($groups, 1), $pages)->assertSessionHasErrors('groups');

        // A page moved into another group.
        $academic = $this->id('academic_management');
        $people = $this->id('people_management');
        $moved = $pages;
        $moved[$people][] = array_pop($moved[$academic]);
        $this->save($admin, $groups, $moved)->assertSessionHasErrors('groups');

        $this->assertSame($before, Menu::query()->orderBy('id')->pluck('sort_order', 'id')->all());
    }

    public function test_only_super_admin_can_change_the_order(): void
    {
        [$groups, $pages] = $this->currentOrder();
        $user = User::factory()->create([
            'school_id' => School::factory()->create()->getKey(),
            'role_id' => Role::factory()->platform()->allSchoolMenus()->create()->getKey(),
        ]);

        $this->actingAs($user)->get(route('platform.menus.order'))->assertForbidden();
        $this->save($user, array_reverse($groups), $pages)->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('platform.menus.order'))->assertOk()->assertSee('Academic Management')->assertSee('Timetable');
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, array<int, int>>}
     */
    private function currentOrder(): array
    {
        $groups = Menu::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $pages = [];

        foreach ($groups as $groupId) {
            $pages[$groupId] = Menu::query()->where('parent_id', $groupId)->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return [$groups, $pages];
    }

    /**
     * @param  array<int, int>  $groups
     * @param  array<int, array<int, int>>  $pages
     */
    private function save(User $user, array $groups, array $pages): TestResponse
    {
        return $this->actingAs($user)->withSession(['_token' => 't'])
            ->put(route('platform.menus.order.update'), ['_token' => 't', 'groups' => $groups, 'pages' => $pages]);
    }

    private function id(string $key): int
    {
        return (int) Menu::query()->where('key', $key)->value('id');
    }
}
