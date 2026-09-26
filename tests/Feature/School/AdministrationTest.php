<?php

namespace Tests\Feature\School;

use App\Enums\UserStatus;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    private School $school;

    private User $admin;

    private Role $adminRole;

    private Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
        $this->school = School::factory()->create(['name' => 'Green Valley School']);
        $this->school->menus()->attach(Menu::query()->whereIn('key', ['users', 'roles', 'audit_logs', 'school_settings', 'students'])->pluck('id'));

        $this->adminRole = $this->schoolRole('School Admin', allMenus: true);
        $this->teacherRole = $this->schoolRole('Teacher');
        $this->admin = $this->schoolUser('Asha Admin', $this->adminRole);
    }

    public function test_admin_creates_a_user_with_a_school_role_only(): void
    {
        $platformRole = Role::factory()->platform()->create(['name' => 'Principal']);
        $otherSchoolRole = Role::factory()->create(['school_id' => School::factory()->create()->getKey(), 'name' => 'Clerk']);

        $this->post(route('school.users.store'), $this->userData(['role_id' => $platformRole->getKey()]), $this->as())
            ->assertSessionHasErrors('role_id');
        $this->post(route('school.users.store'), $this->userData(['role_id' => $otherSchoolRole->getKey()]), $this->as())
            ->assertSessionHasErrors('role_id');

        $this->post(route('school.users.store'), $this->userData(['role_id' => $this->teacherRole->getKey()]), $this->as())
            ->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'ravi@example.com')->sole();
        $this->assertSame($this->school->getKey(), $user->school_id);
        $this->assertSame($this->teacherRole->getKey(), $user->role_id);
        $this->assertFalse($user->isSuperAdmin());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'school_id' => $this->school->getKey()]);
    }

    public function test_users_are_deactivated_never_deleted_and_lose_access_at_once(): void
    {
        $this->assertFalse(Route::has('school.users.destroy'));
        $this->assertFalse(Route::has('school.roles.destroy'));
        $this->assertSame('view,create,edit', Menu::query()->where('key', 'users')->value('actions'));

        $teacher = $this->schoolUser('Ravi Teacher', $this->teacherRole);

        $this->patch(route('school.users.status', $teacher), [], $this->as())->assertRedirect();
        $this->assertSame(UserStatus::Inactive, $teacher->refresh()->status);
        $this->assertModelExists($teacher);

        // Already signed in: the next request signs them out.
        $this->actingAs($teacher)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => $teacher->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->patch(route('school.users.status', $teacher), [], $this->as());
        $this->assertSame(UserStatus::Active, $teacher->refresh()->status);
    }

    public function test_nobody_changes_their_own_role_status_or_access(): void
    {
        $this->patch(route('school.users.status', $this->admin), [], $this->as())->assertForbidden();

        $this->put(route('school.users.update', $this->admin), [
            'name' => 'Asha A.',
            'email' => $this->admin->email,
            'role_id' => $this->teacherRole->getKey(),
            'status' => 'inactive',
        ], $this->as())->assertSessionHasNoErrors();

        $this->admin->refresh();
        $this->assertSame('Asha A.', $this->admin->name);
        $this->assertSame($this->adminRole->getKey(), $this->admin->role_id);
        $this->assertSame(UserStatus::Active, $this->admin->status);

        $this->put(route('school.roles.update', $this->adminRole), ['name' => 'Boss', 'all_school_menus' => 1, 'is_active' => 1], $this->as())
            ->assertForbidden();
        $this->actingAs($this->admin)->get(route('school.roles.edit', $this->adminRole))->assertOk()->assertSee('This is your own role');
    }

    public function test_platform_role_users_are_read_only_and_other_schools_are_hidden(): void
    {
        $principal = $this->schoolUser('Pooja Principal', Role::factory()->platform()->create(['name' => 'Principal']));
        $stranger = User::factory()->create(['school_id' => School::factory()->create()->getKey(), 'name' => 'Stranger Danger']);

        $this->actingAs($this->admin)->get(route('school.users.edit', $principal))->assertOk()->assertSee('Managed by the platform');
        $this->put(route('school.users.update', $principal), $this->userData(['email' => $principal->email]), $this->as())->assertForbidden();
        $this->patch(route('school.users.status', $principal), [], $this->as())->assertForbidden();

        $this->actingAs($this->admin)->get(route('school.users.index'))->assertOk()->assertSee('Pooja Principal')->assertDontSee('Stranger Danger');
        $this->actingAs($this->admin)->get(route('school.users.edit', $stranger))->assertNotFound();
    }

    public function test_user_list_searches_filters_and_paginates_on_the_server(): void
    {
        foreach (range(1, 21) as $number) {
            $this->schoolUser(sprintf('Teacher %02d', $number), $this->teacherRole);
        }
        $this->schoolUser('Zoya Inactive', $this->teacherRole, UserStatus::Inactive);

        $this->actingAs($this->admin)->get(route('school.users.index', ['role_id' => $this->teacherRole->getKey(), 'status' => 'active']))
            ->assertOk()->assertSee('Teacher 01')->assertDontSee('Teacher 21')->assertDontSee('Zoya Inactive');
        $this->actingAs($this->admin)->get(route('school.users.index', ['search' => 'zoya']))
            ->assertOk()->assertSee('Zoya Inactive')->assertDontSee('Teacher 01');
    }

    public function test_school_roles_cannot_copy_platform_roles_or_reach_other_schools(): void
    {
        $platformRole = Role::factory()->platform()->create(['name' => 'Principal']);
        $foreignMenu = Menu::query()->where('key', 'fee_heads')->value('id');
        $studentsMenu = Menu::query()->where('key', 'students')->value('id');

        $this->post(route('school.roles.store'), ['name' => 'principal', 'is_active' => 1], $this->as())->assertSessionHasErrors('name');

        $this->post(route('school.roles.store'), [
            'name' => 'Accountant',
            'is_active' => 1,
            'menus' => [$studentsMenu => ['view' => '1', 'export' => '1'], $foreignMenu => ['view' => '1']],
        ], $this->as())->assertRedirect()->assertSessionHasNoErrors();

        $role = Role::query()->where('name', 'Accountant')->sole();
        $this->assertSame($this->school->getKey(), $role->school_id);
        $this->assertDatabaseHas('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $studentsMenu, 'can_view' => true, 'can_export' => true]);
        $this->assertDatabaseMissing('role_menus', ['role_id' => $role->getKey(), 'menu_id' => $foreignMenu]);

        $this->actingAs($this->admin)->get(route('school.roles.index'))->assertOk()->assertSee('Accountant')->assertDontSee('Principal');
        $this->actingAs($this->admin)->get(route('school.roles.edit', $platformRole))->assertNotFound();

        $this->put(route('school.roles.update', $role), ['name' => 'Accountant', 'is_active' => 0], $this->as())->assertSessionHasNoErrors();
        $this->assertFalse($role->refresh()->is_active);
        $this->assertModelExists($role);
    }

    public function test_audit_logs_show_only_this_school_and_export_needs_permission(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record(event: 'student.created', actor: $this->admin, school: $this->school);
        $logger->record(event: 'secret.elsewhere', school: School::factory()->create());

        $this->actingAs($this->admin)->get(route('school.audit-logs.index'))
            ->assertOk()->assertSee('Student created')->assertDontSee('Secret elsewhere');

        $csv = $this->actingAs($this->admin)->get(route('school.audit-logs.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('student.created', $csv);
        $this->assertStringNotContainsString('secret.elsewhere', $csv);

        $viewer = $this->schoolUser('Log Viewer', $this->roleWith(['audit_logs' => ['can_view' => true]]));
        $this->actingAs($viewer)->get(route('school.audit-logs.index'))->assertOk()->assertDontSee('Export CSV');
        $this->actingAs($viewer)->get(route('school.audit-logs.export'))->assertForbidden();
    }

    public function test_settings_save_school_details_but_not_platform_fields(): void
    {
        $this->school->update(['settings' => ['kept_by_platform' => 'yes']]);

        $this->put(route('school.settings.update'), [
            'name' => 'Green Valley Public School',
            'timezone' => 'Asia/Kolkata',
            'principal_name' => 'Dr. Meena Rao',
            'board' => 'CBSE',
            'website' => 'https://gvps.example.com',
            'established_year' => '1998',
            'admission_no_prefix' => 'gv',
            'working_days' => ['1', '2', '3', '4', '5'],
            'status' => 'active',
            'max_students' => 99999,
        ], $this->as())->assertRedirect(route('school.settings.edit'))->assertSessionHasNoErrors();

        $school = $this->school->refresh();
        $this->assertSame('Green Valley Public School', $school->name);
        $this->assertSame('Dr. Meena Rao', $school->setting('principal_name'));
        $this->assertSame(1998, $school->setting('established_year'));
        $this->assertSame('GV', $school->admissionPrefix());
        $this->assertSame([1, 2, 3, 4, 5], $school->workingDays());
        $this->assertSame('yes', $school->setting('kept_by_platform'));
        $this->assertNotSame(99999, $school->max_students);
        $this->assertSame('trial', $school->status->value);

        $this->put(route('school.settings.update'), ['name' => 'X', 'timezone' => 'Mars/Base', 'website' => 'not a url', 'admission_no_prefix' => 'A-1'], $this->as())
            ->assertSessionHasErrors(['timezone', 'website', 'admission_no_prefix', 'working_days']);

        $viewer = $this->schoolUser('Settings Viewer', $this->roleWith(['school_settings' => ['can_view' => true]]));
        $this->actingAs($viewer)->get(route('school.settings.edit'))->assertOk()->assertDontSee('Save settings');
        $this->actingAs($viewer)->withSession(['_token' => 't'])->put(route('school.settings.update'), ['_token' => 't', 'name' => 'Hacked'])->assertForbidden();
    }

    public function test_sidebar_lists_the_administration_pages(): void
    {
        $this->actingAs($this->admin)->get(route('school.dashboard'))
            ->assertOk()
            ->assertSee(route('school.users.index'))
            ->assertSee(route('school.roles.index'))
            ->assertSee(route('school.audit-logs.index'))
            ->assertSee(route('school.settings.edit'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function userData(array $overrides = []): array
    {
        return [
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'status' => 'active',
            ...$overrides,
        ];
    }

    /**
     * Signs in as the school admin with a CSRF token; returns the matching headers.
     *
     * @return array<string, string>
     */
    private function as(): array
    {
        $this->actingAs($this->admin)->withSession(['_token' => 't']);

        return ['X-CSRF-TOKEN' => 't'];
    }

    private function schoolRole(string $name, bool $allMenus = false): Role
    {
        return Role::factory()->create(['school_id' => $this->school->getKey(), 'name' => $name, 'all_school_menus' => $allMenus]);
    }

    /**
     * @param  array<string, array<string, bool>>  $grants
     */
    private function roleWith(array $grants): Role
    {
        $role = $this->schoolRole('Limited '.count(Role::all()));

        foreach ($grants as $key => $columns) {
            $role->menus()->attach(Menu::query()->where('key', $key)->value('id'), $columns);
        }

        return $role;
    }

    private function schoolUser(string $name, Role $role, UserStatus $status = UserStatus::Active): User
    {
        return User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => $role->getKey(),
            'name' => $name,
            'status' => $status,
            'password' => self::PASSWORD,
        ]);
    }
}
