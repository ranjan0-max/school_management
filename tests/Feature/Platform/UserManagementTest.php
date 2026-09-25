<?php

namespace Tests\Feature\Platform;

use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
    }

    public function test_regular_user_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->create())->get(route('platform.users.index'))->assertForbidden();
    }

    public function test_super_admin_creates_user_with_school_and_platform_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        $principal = Role::factory()->platform()->allSchoolMenus()->create(['name' => 'Principal']);

        $this->actingAs($admin)->get(route('platform.users.create'))->assertOk();

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.users.store'), [
            '_token' => 't',
            'name' => 'Asha Verma',
            'email' => 'ASHA@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'school_id' => $school->getKey(),
            'role_id' => $principal->getKey(),
            'status' => 'active',
        ])->assertRedirect();

        $user = User::query()->where('email', 'asha@example.com')->sole();

        $this->assertSame((int) $school->getKey(), (int) $user->school_id);
        $this->assertSame((int) $principal->getKey(), (int) $user->role_id);
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created']);

        $this->actingAs($admin)->get(route('platform.users.index'))->assertOk()->assertSee('Asha Verma');
    }

    public function test_role_from_another_school_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        $foreignRole = Role::factory()->create(['school_id' => School::factory()->create()->getKey()]);

        $this->actingAs($admin)->withSession(['_token' => 't'])->post(route('platform.users.store'), [
            '_token' => 't',
            'name' => 'Ravi',
            'email' => 'ravi@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'school_id' => $school->getKey(),
            'role_id' => $foreignRole->getKey(),
            'status' => 'active',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'ravi@example.com']);
    }

    public function test_overrides_are_saved_only_for_school_menus_and_supported_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        $school->menus()->attach([$this->menuId('marks_entry'), $this->menuId('fee_payments')]);
        $user = User::factory()->create(['school_id' => $school->getKey()]);

        $this->actingAs($admin)->get(route('platform.users.edit', $user))->assertOk()->assertSee('Marks Entry');

        $this->actingAs($admin)->withSession(['_token' => 't'])->put(route('platform.users.update', $user), [
            '_token' => 't',
            'name' => $user->name,
            'email' => $user->email,
            'school_id' => $school->getKey(),
            'status' => 'active',
            'overrides' => [
                $this->menuId('marks_entry') => ['view' => 'allow', 'create' => 'allow', 'delete' => 'allow'],
                $this->menuId('fee_payments') => ['approve' => 'deny'],
                $this->menuId('hostel') => ['view' => 'allow'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('user_menu_overrides', [
            'user_id' => $user->getKey(), 'menu_id' => $this->menuId('marks_entry'),
            'can_view' => true, 'can_create' => true, 'can_delete' => null,
        ]);
        $this->assertDatabaseHas('user_menu_overrides', [
            'user_id' => $user->getKey(), 'menu_id' => $this->menuId('fee_payments'), 'can_approve' => false, 'can_view' => null,
        ]);
        $this->assertDatabaseMissing('user_menu_overrides', ['user_id' => $user->getKey(), 'menu_id' => $this->menuId('hostel')]);
    }

    public function test_super_admin_account_cannot_be_edited_from_users_screen(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('platform.users.edit', $otherAdmin))->assertNotFound();
    }

    public function test_password_is_kept_when_left_empty(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->getKey()]);
        $originalHash = $user->password;

        $this->actingAs($admin)->withSession(['_token' => 't'])->put(route('platform.users.update', $user), [
            '_token' => 't',
            'name' => 'Updated Name',
            'email' => $user->email,
            'school_id' => $school->getKey(),
            'status' => 'inactive',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('inactive', $user->status->value);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_user_list_filters_and_paginates_on_the_server(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->create();
        User::factory()->count(22)->create(['school_id' => $school->getKey()]);
        User::factory()->inactive()->create(['school_id' => $school->getKey(), 'name' => 'Inactive Person']);

        $this->actingAs($admin)->get(route('platform.users.index'))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->count() === 20 && $users->total() === 23);

        $this->actingAs($admin)->get(route('platform.users.index', ['status' => 'inactive']))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 1)->assertSee('Inactive Person');

        $this->actingAs($admin)->get(route('platform.users.index', ['search' => 'Inactive Pers']))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 1);
    }

    private function menuId(string $key): int
    {
        return (int) Menu::query()->where('key', $key)->value('id');
    }
}
