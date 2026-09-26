<?php

namespace Tests\Feature\Platform;

use App\Models\Menu;
use App\Models\School;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SchoolAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
    }

    public function test_regular_user_cannot_change_school_menus(): void
    {
        $school = School::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('platform.schools.access', $school))->assertForbidden();
    }

    public function test_super_admin_assigns_and_removes_school_menus(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        $students = $this->menuId('students');
        $library = $this->menuId('library');

        $this->actingAs($admin)->get(route('platform.schools.access', $school))->assertOk()->assertSee('People Management');

        $this->update($admin, $school, [$students, $library])->assertRedirect();
        $this->assertEqualsCanonicalizing([$students, $library], $school->menus()->pluck('menus.id')->all());

        $this->update($admin, $school, [$students])->assertRedirect();
        $this->assertSame([$students], $school->menus()->pluck('menus.id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'school.access.updated']);
    }

    public function test_group_rows_cannot_be_assigned(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();

        $this->update($admin, $school, [$this->menuId('people_management')])->assertSessionHasErrors('menu_ids.0');
        $this->assertSame(0, $school->menus()->count());
    }

    public function test_super_admin_can_enter_and_leave_a_school_workspace(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();

        $this->actingAs($admin)->withSession(['_token' => 't'])
            ->post(route('platform.schools.enter', $school), ['_token' => 't'])
            ->assertRedirect(route('school.dashboard'));

        $this->actingAs($admin)->get(route('school.dashboard'))->assertOk()->assertSee($school->name);

        $this->actingAs($admin)->withSession(['_token' => 't', 'active_school_id' => $school->getKey()])
            ->delete(route('platform.schools.leave'), ['_token' => 't'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'school.context_entered']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'school.context_exited']);
    }

    public function test_school_switcher_searches_and_pages_schools_on_the_server(): void
    {
        $admin = User::factory()->superAdmin()->create();
        School::factory()->count(21)->sequence(fn ($sequence) => ['name' => sprintf('Alpha %02d', $sequence->index)])->create();
        $beta = School::factory()->create(['name' => 'Beta Public School', 'code' => 'BPS']);

        $this->actingAs($admin)->getJson(route('platform.schools.options'))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.name', 'Alpha 00')
            ->assertJsonPath('next_page_url', route('platform.schools.options', ['page' => 2]));

        $this->actingAs($admin)->getJson(route('platform.schools.options', ['page' => 2]))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('next_page_url', null);

        $this->actingAs($admin)->getJson(route('platform.schools.options', ['search' => 'bps']))
            ->assertExactJson([
                'data' => [[
                    'id' => $beta->getKey(),
                    'name' => 'Beta Public School',
                    'code' => 'BPS',
                    'status' => $beta->status->value,
                    'enter_url' => route('platform.schools.enter', $beta),
                ]],
                'next_page_url' => null,
            ]);

        $this->actingAs(User::factory()->create())->getJson(route('platform.schools.options'))->assertForbidden();
    }

    public function test_top_bar_school_switcher_is_only_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create(['name' => 'Green Valley School']);

        $this->actingAs($admin)->get(route('platform.dashboard'))
            ->assertOk()->assertSee('data-school-switcher', false)->assertSee('Select school');

        $this->actingAs($admin)->withSession(['active_school_id' => $school->getKey()])
            ->get(route('platform.dashboard'))
            ->assertSee('Working in Green Valley School')->assertSee(route('platform.schools.leave'));

        $this->actingAs(User::factory()->create(['school_id' => $school->getKey()]))
            ->get(route('school.dashboard'))
            ->assertOk()->assertDontSee('data-school-switcher', false);
    }

    /**
     * @param  array<int, int>  $menuIds
     */
    private function update(User $admin, School $school, array $menuIds): TestResponse
    {
        return $this->actingAs($admin)->withSession(['_token' => 't'])
            ->put(route('platform.schools.access.update', $school), ['_token' => 't', 'menu_ids' => $menuIds]);
    }

    private function menuId(string $key): int
    {
        return (int) Menu::query()->where('key', $key)->value('id');
    }
}
