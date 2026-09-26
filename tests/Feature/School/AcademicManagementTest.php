<?php

namespace Tests\Feature\School;

use App\Models\AcademicSession;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicManagementTest extends TestCase
{
    use RefreshDatabase;

    private const ACADEMIC_PAGES = ['academic_sessions', 'classes', 'sections', 'subjects'];

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
        $this->school = $this->schoolWithAcademicMenus();
        $this->admin = $this->userOf($this->school, Role::factory()->platform()->allSchoolMenus()->create());
    }

    public function test_pages_require_menu_access(): void
    {
        $noRole = $this->userOf($this->school, null);
        $viewer = $this->userOf($this->school, $this->roleWith(['classes' => ['can_view' => true]]));

        $this->actingAs($noRole)->get(route('school.classes.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('school.classes.index'))->assertOk()->assertDontSee('+ Add class');
        $this->actingAs($viewer)->get(route('school.classes.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('school.sections.index'))->assertForbidden();
    }

    public function test_menus_not_assigned_to_the_school_are_blocked_even_for_all_menus_role(): void
    {
        $school = School::factory()->create();
        $principal = $this->userOf($school, Role::factory()->platform()->allSchoolMenus()->create());

        $this->actingAs($principal)->get(route('school.subjects.index'))->assertForbidden();
    }

    public function test_sidebar_lists_academic_pages_for_allowed_users(): void
    {
        $this->actingAs($this->admin)->get(route('school.dashboard'))
            ->assertOk()->assertSee('Academic Management')->assertSee(route('school.classes.index'));
    }

    public function test_only_one_session_is_current_and_current_cannot_be_deleted(): void
    {
        $this->post(route('school.academic-sessions.store'), $this->sessionData('2025-26', true), $this->as());
        $this->post(route('school.academic-sessions.store'), $this->sessionData('2026-27', true), $this->as());

        $old = AcademicSession::query()->where('name', '2025-26')->sole();
        $new = AcademicSession::query()->where('name', '2026-27')->sole();

        $this->assertFalse($old->is_current);
        $this->assertTrue($new->is_current);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.academic-sessions.destroy', $new), ['_token' => 't'])
            ->assertSessionHas('error');
        $this->assertModelExists($new);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->patch(route('school.academic-sessions.current', $old), ['_token' => 't']);
        $this->assertTrue($old->fresh()?->is_current);
        $this->assertFalse($new->fresh()?->is_current);
        $this->assertDatabaseHas('audit_logs', ['event' => 'academic_session.made_current']);
    }

    public function test_session_end_must_be_after_start(): void
    {
        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->post(route('school.academic-sessions.store'), [...$this->sessionData('Bad'), 'ends_on' => '2026-01-01', 'starts_on' => '2026-04-01'])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_another_schools_records_are_hidden_and_untouchable(): void
    {
        $otherClass = SchoolClass::query()->create(['school_id' => School::factory()->create()->getKey(), 'name' => 'Other Class 1']);

        $this->actingAs($this->admin)->get(route('school.classes.index'))->assertOk()->assertDontSee('Other Class 1');
        $this->actingAs($this->admin)->get(route('school.classes.edit', $otherClass))->assertNotFound();
        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.classes.destroy', $otherClass), ['_token' => 't'])->assertNotFound();

        $this->assertModelExists($otherClass);
    }

    public function test_class_subjects_are_limited_to_the_schools_subjects(): void
    {
        $maths = Subject::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Maths', 'type' => 'theory']);
        $foreign = Subject::query()->create(['school_id' => School::factory()->create()->getKey(), 'name' => 'Foreign', 'type' => 'theory']);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->post(route('school.classes.store'), ['_token' => 't', 'name' => 'Class 5', 'sort_order' => 5, 'subject_ids' => [$maths->getKey(), $foreign->getKey()]])
            ->assertSessionHasErrors('subject_ids.1');

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->post(route('school.classes.store'), ['_token' => 't', 'name' => 'Class 5', 'sort_order' => 5, 'subject_ids' => [$maths->getKey()]])
            ->assertRedirect(route('school.classes.index'));

        $class = SchoolClass::query()->where('name', 'Class 5')->sole();
        $this->assertSame([(int) $maths->getKey()], $class->subjects()->pluck('subjects.id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('class_subjects', ['class_id' => $class->getKey(), 'school_id' => $this->school->getKey()]);
    }

    public function test_class_with_sections_cannot_be_deleted(): void
    {
        $class = SchoolClass::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Class 1']);
        Section::query()->create(['school_id' => $this->school->getKey(), 'class_id' => $class->getKey(), 'name' => 'A']);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.classes.destroy', $class), ['_token' => 't'])
            ->assertSessionHas('error');

        $this->assertModelExists($class);
    }

    public function test_section_names_are_unique_per_class_and_class_must_belong_to_school(): void
    {
        $classOne = SchoolClass::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Class 1']);
        $classTwo = SchoolClass::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Class 2']);
        $foreignClass = SchoolClass::query()->create(['school_id' => School::factory()->create()->getKey(), 'name' => 'Class 1']);

        $this->post(route('school.sections.store'), ['class_id' => $classOne->getKey(), 'name' => 'A'], $this->as())->assertRedirect();
        $this->post(route('school.sections.store'), ['class_id' => $classOne->getKey(), 'name' => 'A'], $this->as())->assertSessionHasErrors('name');
        $this->post(route('school.sections.store'), ['class_id' => $classTwo->getKey(), 'name' => 'A'], $this->as())->assertSessionHasNoErrors();
        $this->post(route('school.sections.store'), ['class_id' => $foreignClass->getKey(), 'name' => 'B'], $this->as())->assertSessionHasErrors('class_id');

        $this->assertSame(2, Section::query()->where('school_id', $this->school->getKey())->count());
    }

    public function test_subject_list_is_searchable_filterable_and_paginated(): void
    {
        foreach (range(1, 22) as $number) {
            Subject::query()->create(['school_id' => $this->school->getKey(), 'name' => "Elective {$number}", 'type' => 'theory']);
        }
        Subject::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Chemistry Lab', 'code' => 'CHEM', 'type' => 'practical']);

        $this->actingAs($this->admin)->get(route('school.subjects.index'))
            ->assertOk()->assertViewHas('subjects', fn ($subjects) => $subjects->count() === 20 && $subjects->total() === 23);
        $this->actingAs($this->admin)->get(route('school.subjects.index', ['search' => 'chem']))
            ->assertViewHas('subjects', fn ($subjects) => $subjects->total() === 1);
        $this->actingAs($this->admin)->get(route('school.subjects.index', ['type' => 'practical']))
            ->assertViewHas('subjects', fn ($subjects) => $subjects->total() === 1);
    }

    public function test_subject_code_is_unique_per_school(): void
    {
        Subject::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Maths', 'code' => 'MATH', 'type' => 'theory']);

        $this->post(route('school.subjects.store'), ['name' => 'Algebra', 'code' => 'math', 'type' => 'theory'], $this->as())
            ->assertSessionHasErrors('code');
    }

    public function test_super_admin_sees_school_menus_everywhere(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get(route('platform.dashboard'))
            ->assertOk()->assertSee(route('school.classes.index'));

        $this->actingAs($superAdmin)->withSession(['active_school_id' => $this->school->getKey()])
            ->get(route('school.dashboard'))
            ->assertOk()->assertSee(route('school.classes.index'));
    }

    public function test_super_admin_without_a_school_picks_one_then_lands_on_the_requested_page(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get(route('school.classes.index'))
            ->assertRedirect(route('platform.schools.index'));

        $this->actingAs($superAdmin)->withSession(['_token' => 't'])
            ->post(route('platform.schools.enter', $this->school), ['_token' => 't'])
            ->assertRedirect(route('school.classes.index'));
    }

    public function test_super_admin_can_work_inside_an_entered_school(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->withSession(['active_school_id' => $this->school->getKey(), '_token' => 't'])
            ->post(route('school.subjects.store'), ['_token' => 't', 'name' => 'English', 'type' => 'theory'])
            ->assertRedirect(route('school.subjects.index'));

        $this->assertDatabaseHas('subjects', ['school_id' => $this->school->getKey(), 'name' => 'English']);
    }

    /**
     * Acts as the school admin and posts with a CSRF token.
     *
     * @return array<string, string>
     */
    private function as(): array
    {
        $this->actingAs($this->admin)->withSession(['_token' => 't']);

        return ['X-CSRF-TOKEN' => 't'];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(string $name, bool $current = false): array
    {
        return ['name' => $name, 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => $current ? 1 : 0];
    }

    private function schoolWithAcademicMenus(): School
    {
        $school = School::factory()->create();
        $school->menus()->attach(Menu::query()->whereIn('key', self::ACADEMIC_PAGES)->pluck('id'));

        return $school;
    }

    /**
     * @param  array<string, array<string, bool>>  $grants
     */
    private function roleWith(array $grants): Role
    {
        $role = Role::factory()->create(['school_id' => $this->school->getKey()]);

        foreach ($grants as $key => $columns) {
            $role->menus()->attach(Menu::query()->where('key', $key)->value('id'), $columns);
        }

        return $role;
    }

    private function userOf(School $school, ?Role $role): User
    {
        return User::factory()->create(['school_id' => $school->getKey(), 'role_id' => $role?->getKey()]);
    }
}
