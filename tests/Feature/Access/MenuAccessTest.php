<?php

namespace Tests\Feature\Access;

use App\Enums\MenuAction;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Access\MenuAccess;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuAccessTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
        $this->school = School::factory()->create();
        $this->school->menus()->attach($this->menuIds(['students', 'fee_payments']));
    }

    public function test_super_admin_is_always_allowed(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($this->access()->allows($admin, 'hostel', MenuAction::Delete));
    }

    public function test_role_grants_only_the_ticked_actions(): void
    {
        $role = $this->schoolRole(['students' => ['can_view' => true, 'can_create' => true]]);
        $user = $this->userWith($role);

        $this->assertTrue($this->access()->allows($user, 'students', MenuAction::View));
        $this->assertTrue($this->access()->allows($user, 'students', MenuAction::Create));
        $this->assertFalse($this->access()->allows($user, 'students', MenuAction::Delete));
        $this->assertFalse($this->access()->allows($user, 'fee_payments', MenuAction::View));
    }

    public function test_role_cannot_reach_menus_the_school_was_not_given(): void
    {
        $role = $this->schoolRole(['hostel' => ['can_view' => true]]);

        $this->assertFalse($this->access()->allows($this->userWith($role), 'hostel'));
    }

    public function test_all_school_menus_role_stays_inside_the_school_boundary(): void
    {
        $principal = Role::factory()->platform()->allSchoolMenus()->create();
        $user = $this->userWith($principal);

        $this->assertTrue($this->access()->allows($user, 'fee_payments', MenuAction::Approve));
        $this->assertFalse($this->access()->allows($user, 'hostel'));
        $this->assertFalse($user->fresh()?->isSuperAdmin());
    }

    public function test_action_the_page_does_not_support_is_never_granted(): void
    {
        $principal = Role::factory()->platform()->allSchoolMenus()->create();

        // Payments support view, create and approve only.
        $this->assertFalse($this->access()->allows($this->userWith($principal), 'fee_payments', MenuAction::Delete));
    }

    public function test_user_override_can_add_and_remove_access(): void
    {
        $role = $this->schoolRole(['students' => ['can_view' => true, 'can_delete' => true]]);
        $user = $this->userWith($role);
        $user->menuOverrides()->attach($this->menuId('students'), ['can_delete' => false]);
        $user->menuOverrides()->attach($this->menuId('fee_payments'), ['can_view' => true, 'can_create' => true]);

        $this->assertTrue($this->access()->allows($user, 'students'));
        $this->assertFalse($this->access()->allows($user, 'students', MenuAction::Delete));
        $this->assertTrue($this->access()->allows($user, 'fee_payments', MenuAction::Create));
        $this->assertFalse($this->access()->allows($user, 'fee_payments', MenuAction::Approve));
    }

    public function test_override_cannot_reach_menus_outside_the_school(): void
    {
        $user = $this->userWith(null);
        $user->menuOverrides()->attach($this->menuId('hostel'), ['can_view' => true]);

        $this->assertFalse($this->access()->allows($user, 'hostel'));
    }

    public function test_inactive_role_grants_nothing(): void
    {
        $role = Role::factory()->platform()->allSchoolMenus()->inactive()->create();

        $this->assertFalse($this->access()->allows($this->userWith($role), 'students'));
    }

    public function test_role_of_another_school_grants_nothing(): void
    {
        $otherSchoolRole = Role::factory()->allSchoolMenus()->create();

        $this->assertFalse($this->access()->allows($this->userWith($otherSchoolRole), 'students'));
    }

    public function test_menu_middleware_blocks_users_without_access(): void
    {
        $this->app['router']->get('/_test/students', fn () => 'ok')->middleware(['web', 'auth', 'menu:students,create']);

        $viewer = $this->userWith($this->schoolRole(['students' => ['can_view' => true]]));
        $creator = $this->userWith($this->schoolRole(['students' => ['can_view' => true, 'can_create' => true]]));

        $this->actingAs($viewer)->get('/_test/students')->assertForbidden();
        $this->actingAs($creator)->get('/_test/students')->assertOk();
    }

    private function access(): MenuAccess
    {
        return new MenuAccess;
    }

    /**
     * @param  array<string, array<string, bool>>  $grants
     */
    private function schoolRole(array $grants): Role
    {
        $role = Role::factory()->create(['school_id' => $this->school->getKey()]);

        foreach ($grants as $key => $columns) {
            $role->menus()->attach($this->menuId($key), $columns);
        }

        return $role;
    }

    private function userWith(?Role $role): User
    {
        return User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => $role?->getKey(),
        ]);
    }

    private function menuId(string $key): int
    {
        return (int) Menu::query()->where('key', $key)->value('id');
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, int>
     */
    private function menuIds(array $keys): array
    {
        return array_map(fn (string $key): int => $this->menuId($key), $keys);
    }
}
