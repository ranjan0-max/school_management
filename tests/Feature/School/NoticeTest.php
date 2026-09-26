<?php

namespace Tests\Feature\School;

use App\Enums\NoticeStatus;
use App\Models\Menu;
use App\Models\Notice;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $principal;

    private User $teacher;

    private User $accountant;

    private Role $teacherRole;

    private Role $accountantRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-27 05:00:00');
        $this->seed(NavigationSeeder::class);
        $this->school = School::factory()->create(['timezone' => 'Asia/Kolkata']);
        $this->school->menus()->attach(Menu::query()->where('key', 'notices')->pluck('id'));

        $this->principal = $this->userWith('Principal', ['can_view' => true, 'can_create' => true, 'can_edit' => true]);
        $this->teacherRole = $this->role('Teacher');
        $this->accountantRole = $this->role('Accountant');
        $this->teacher = User::factory()->create(['school_id' => $this->school->getKey(), 'role_id' => $this->teacherRole->getKey(), 'name' => 'Tara Teacher']);
        $this->accountant = User::factory()->create(['school_id' => $this->school->getKey(), 'role_id' => $this->accountantRole->getKey(), 'name' => 'Arun Accounts']);
    }

    public function test_notice_reaches_only_the_chosen_roles(): void
    {
        $class = SchoolClass::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Class 5']);

        $this->saveNotice(['title' => 'Staff meeting at 3 PM', 'audience' => 'selected', 'role_ids' => [$this->teacherRole->getKey()], 'class_ids' => [$class->getKey()]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $notice = Notice::query()->sole();
        $this->assertSame(NoticeStatus::Published, $notice->status);
        $this->assertEqualsCanonicalizing(
            [['role', $this->teacherRole->getKey()], ['class', $class->getKey()]],
            $notice->audiences->map(fn ($row) => [$row->target_type, (int) $row->target_id])->all(),
        );

        $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertOk()->assertSee('Staff meeting at 3 PM');
        $this->actingAs($this->accountant)->get(route('school.notices.index'))->assertOk()->assertDontSee('Staff meeting at 3 PM');
        $this->actingAs($this->accountant)->getJson(route('school.notices.preview', $notice))->assertNotFound();

        $this->actingAs($this->teacher)->getJson(route('school.notices.preview', $notice))->assertOk();
        $this->assertDatabaseHas('notice_reads', ['notice_id' => $notice->getKey(), 'user_id' => $this->teacher->getKey()]);

        $this->actingAs($this->principal)->get(route('school.notices.index', ['view' => 'manage']))
            ->assertOk()->assertSee('Teacher · Parents of Class 5');
    }

    public function test_selected_audience_needs_a_role_or_class(): void
    {
        $this->saveNotice(['title' => 'Nobody', 'audience' => 'selected'])->assertSessionHasErrors('audience');
        $this->assertDatabaseCount('notices', 0);
    }

    public function test_pinned_notices_stay_on_top_and_expired_ones_disappear(): void
    {
        $this->saveNotice(['title' => 'Old pinned rule', 'is_pinned' => 1]);
        $this->travel(1)->hours();
        $this->saveNotice(['title' => 'Newest news']);
        $this->saveNotice(['title' => 'Last day today', 'expires_on' => '2026-09-27']);

        $html = $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Newest news'), strpos($html, 'Old pinned rule'));
        $this->assertStringContainsString('Last day today', $html);

        $this->travelTo('2026-09-28 05:00:00');
        $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertOk()->assertDontSee('Last day today');
        $this->actingAs($this->principal)->get(route('school.notices.index', ['view' => 'manage', 'state' => 'expired']))
            ->assertOk()->assertSee('Last day today')->assertDontSee('Newest news');

        // The dashboard shows the same board.
        $this->actingAs($this->teacher)->get(route('school.dashboard'))->assertOk()->assertSee('Old pinned rule')->assertSee('Notice board');
    }

    public function test_drafts_and_scheduled_notices_are_hidden_until_published(): void
    {
        $this->saveNotice(['title' => 'Draft idea', 'publish_mode' => 'draft']);
        $this->saveNotice(['title' => 'Sports day news', 'publish_mode' => 'schedule', 'publish_at' => '2026-09-28T09:00'])->assertSessionHasNoErrors();
        $this->saveNotice(['title' => 'Too late', 'publish_mode' => 'schedule', 'publish_at' => '2026-09-01T09:00'])->assertSessionHasErrors('publish_at');

        $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertDontSee('Draft idea')->assertDontSee('Sports day news');

        // 09:00 in Kolkata is 03:30 UTC.
        $this->travelTo('2026-09-28 03:31:00');
        $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertSee('Sports day news')->assertDontSee('Draft idea');
    }

    public function test_notices_are_archived_and_restored_never_deleted(): void
    {
        $this->assertFalse(Route::has('school.notices.destroy'));
        $this->saveNotice(['title' => 'Fee reminder', 'is_pinned' => 1]);
        $notice = Notice::query()->sole();

        $this->actingAs($this->principal)->withSession(['_token' => 't'])
            ->patch(route('school.notices.archive', $notice), ['_token' => 't'])->assertRedirect();

        $notice->refresh();
        $this->assertSame(NoticeStatus::Archived, $notice->status);
        $this->assertFalse($notice->is_pinned);
        $this->assertModelExists($notice);
        $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertDontSee('<strong>Fee reminder</strong>', false);

        $this->actingAs($this->principal)->withSession(['_token' => 't'])
            ->patch(route('school.notices.restore', $notice), ['_token' => 't']);
        $this->assertSame(NoticeStatus::Draft, $notice->refresh()->status);
    }

    public function test_only_people_with_create_or_edit_can_manage(): void
    {
        $this->actingAs($this->teacher)->get(route('school.notices.index'))->assertOk()->assertDontSee('New notice')->assertDontSee('>Manage<', false);
        $this->actingAs($this->teacher)->get(route('school.notices.create'))->assertForbidden();
        $this->saveNotice(['title' => 'Sneaky'], $this->teacher)->assertForbidden();

        $other = Notice::query()->create(['school_id' => School::factory()->create()->getKey(), 'title' => 'Other school', 'status' => NoticeStatus::Published]);
        $this->actingAs($this->principal)->getJson(route('school.notices.preview', $other))->assertNotFound();
        $this->assertFalse(Route::has('school.notices.show'));
        $this->actingAs($this->principal)->get(route('school.notices.edit', $other))->assertNotFound();
    }

    public function test_bell_counts_unread_notices_and_the_modal_marks_them_read(): void
    {
        $this->saveNotice(['title' => 'Holiday on Friday', 'body' => "Line one\nLine two", 'is_pinned' => 1]);
        $this->saveNotice(['title' => 'Accounts only', 'audience' => 'selected', 'role_ids' => [$this->accountantRole->getKey()]]);
        $holiday = Notice::query()->where('title', 'Holiday on Friday')->sole();
        $accountsOnly = Notice::query()->where('title', 'Accounts only')->sole();

        $this->actingAs($this->teacher)->get(route('school.dashboard'))
            ->assertOk()->assertSee('data-notice-bell', false)->assertSee('Notices, 1 unread');

        $this->actingAs($this->teacher)->getJson(route('school.notices.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Holiday on Friday')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.0.pinned', true);

        $this->actingAs($this->teacher)->getJson(route('school.notices.preview', $holiday))
            ->assertOk()->assertJsonPath('body', "Line one\nLine two")->assertJsonPath('title', 'Holiday on Friday');
        $this->actingAs($this->teacher)->getJson(route('school.notices.preview', $accountsOnly))->assertNotFound();

        $this->actingAs($this->teacher)->getJson(route('school.notices.feed'))
            ->assertJsonPath('unread', 0)->assertJsonPath('data.0.read', true);
    }

    public function test_bell_is_shown_only_to_people_who_can_see_notices(): void
    {
        $noNotices = User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => Role::factory()->create(['school_id' => $this->school->getKey()])->getKey(),
        ]);

        $this->actingAs($noNotices)->get(route('school.dashboard'))->assertOk()->assertDontSee('data-notice-bell', false);
        $this->actingAs($noNotices)->getJson(route('school.notices.feed'))->assertForbidden();

        // Super Admin sees the bell only inside a school.
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin)->get(route('platform.dashboard'))->assertOk()->assertDontSee('data-notice-bell', false);
        $this->actingAs($superAdmin)->withSession(['active_school_id' => $this->school->getKey()])
            ->get(route('platform.dashboard'))->assertOk()->assertSee('data-notice-bell', false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveNotice(array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->principal)->withSession(['_token' => 't'])->post(route('school.notices.store'), [
            '_token' => 't',
            'audience' => 'school',
            'publish_mode' => 'now',
            ...$data,
        ]);
    }

    private function role(string $name): Role
    {
        $role = Role::factory()->create(['school_id' => $this->school->getKey(), 'name' => $name]);
        $role->menus()->attach(Menu::query()->where('key', 'notices')->value('id'), ['can_view' => true]);

        return $role;
    }

    /**
     * @param  array<string, bool>  $grants
     */
    private function userWith(string $roleName, array $grants): User
    {
        $role = Role::factory()->create(['school_id' => $this->school->getKey(), 'name' => $roleName]);
        $role->menus()->attach(Menu::query()->where('key', 'notices')->value('id'), $grants);

        return User::factory()->create(['school_id' => $this->school->getKey(), 'role_id' => $role->getKey()]);
    }
}
