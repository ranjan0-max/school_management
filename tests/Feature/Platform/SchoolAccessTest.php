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
