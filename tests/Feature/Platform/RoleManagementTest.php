<?php

namespace Tests\Feature\Platform;

use App\Enums\RoleType;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
    }

    public function test_regular_user_cannot_manage_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('platform.roles.index'))->assertForbidden();
        $this->actingAs($user)->withSession(['_token' => 't'])->post(route('platform.roles.store'), [
            '_token' => 't', 'type' => 'platform', 'name' => 'Principal', 'is_active' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('roles', 0);
    }

    public function test_super_admin_creates_platform_role_with_menu_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $students = $this->menuId('guardians');

        $this->actingAs($admin)->get(route('platform.roles.create', ['type' => 'platform']))->assertOk();

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.roles.store'), [
            '_token' => 't',
            'type' => 'platform',
            'name' => 'Principal',
            'all_school_menus' => 1,
            'is_active' => 1,
            'menus' => [$students => ['view' => '1', 'delete' => '1']],
        ])->assertRedirect();

        $role = Role::query()->where('name', 'Principal')->sole();

        $this->assertSame(RoleType::Platform, $role->type);
        $this->assertNull($role->school_id);
        $this->assertTrue($role->all_school_menus);
        $this->assertDatabaseHas('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $students, 'can_view' => true, 'can_delete' => true, 'can_create' => false]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.created', 'actor_id' => $admin->getKey()]);

        $this->actingAs($admin)->get(route('platform.roles.edit', $role))->assertOk()->assertSee('Principal');
        $this->actingAs($admin)->get(route('platform.roles.index'))->assertOk()->assertSee('Principal');
    }

    public function test_school_role_ignores_menus_the_school_does_not_have_and_unsupported_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        $school->menus()->attach($this->menuId('fee_payments'));

        $this->actingAs($admin)->get(route('platform.roles.create', ['type' => 'school', 'school_id' => $school->getKey()]))->assertOk();

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.roles.store'), [
            '_token' => 't',
            'type' => 'school',
            'school_id' => $school->getKey(),
            'name' => 'Accountant',
            'all_school_menus' => 0,
            'is_active' => 1,
            'menus' => [
                $this->menuId('fee_payments') => ['view' => '1', 'create' => '1', 'delete' => '1'],
                $this->menuId('hostel') => ['view' => '1'],
            ],
        ])->assertRedirect();

        $role = Role::query()->where('name', 'Accountant')->sole();

        $this->assertSame((int) $school->getKey(), (int) $role->school_id);
        $this->assertDatabaseHas('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $this->menuId('fee_payments'), 'can_create' => true, 'can_delete' => false]);
        $this->assertDatabaseMissing('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $this->menuId('hostel')]);
    }

    public function test_role_names_are_unique_per_school_and_among_platform_roles(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        Role::factory()->platform()->create(['name' => 'Principal']);
        Role::factory()->create(['school_id' => $school->getKey(), 'name' => 'Teacher']);

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.roles.store'), [
            '_token' => 't', 'type' => 'platform', 'name' => 'Principal', 'all_school_menus' => 0, 'is_active' => 1,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.roles.store'), [
            '_token' => 't', 'type' => 'school', 'school_id' => $school->getKey(), 'name' => 'Teacher', 'all_school_menus' => 0, 'is_active' => 1,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.roles.store'), [
            '_token' => 't', 'type' => 'school', 'school_id' => School::factory()->create()->getKey(), 'name' => 'Teacher', 'all_school_menus' => 0, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
    }

    public function test_updating_a_role_replaces_its_menu_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::factory()->platform()->create(['name' => 'Coordinator']);
        $role->menus()->attach($this->menuId('students'), ['can_view' => true]);

        $this->actingAs($admin)->withSession(['_token' => 't'])->put(route('platform.roles.update', $role), [
            '_token' => 't',
            'name' => 'Coordinator',
            'all_school_menus' => 0,
            'is_active' => 0,
            'menus' => [$this->menuId('notices') => ['view' => '1', 'create' => '1']],
        ])->assertRedirect();

        $this->assertFalse($role->fresh()?->is_active);
        $this->assertDatabaseMissing('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $this->menuId('students')]);
        $this->assertDatabaseHas('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $this->menuId('notices'), 'can_create' => true]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.updated']);
    }

    public function test_role_list_is_searchable_and_paginated_on_the_server(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Role::factory()->platform()->create(['name' => 'Principal']);
        Role::factory()->count(20)->platform()->sequence(fn ($sequence) => ['name' => 'Clerk '.$sequence->index])->create();

        $this->actingAs($admin)->get(route('platform.roles.index', ['search' => 'Princ']))
            ->assertOk()->assertSee('Principal')->assertDontSee('Clerk 1');

        $this->actingAs($admin)->get(route('platform.roles.index'))
            ->assertOk()->assertViewHas('roles', fn ($roles) => $roles->count() === 18 && $roles->total() === 21);
    }

    public function test_role_options_return_only_that_schools_active_roles(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        Role::factory()->create(['school_id' => $school->getKey(), 'name' => 'Teacher']);
        Role::factory()->inactive()->create(['school_id' => $school->getKey(), 'name' => 'Old Role']);
        Role::factory()->create(['name' => 'Other School Role']);
        Role::factory()->platform()->create(['name' => 'Principal']);

        $this->actingAs($admin)->getJson(route('platform.roles.options', ['school_id' => $school->getKey()]))
            ->assertOk()
            ->assertExactJson([['id' => Role::query()->where('name', 'Teacher')->value('id'), 'name' => 'Teacher']]);

        $this->actingAs(User::factory()->create())->getJson(route('platform.roles.options', ['school_id' => $school->getKey()]))
            ->assertForbidden();
    }

    private function menuId(string $key): int
    {
        return (int) Menu::query()->where('key', $key)->value('id');
    }
}
