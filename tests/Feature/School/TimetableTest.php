<?php

namespace Tests\Feature\School;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\Menu;
use App\Models\Period;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private Section $sectionA;

    private Section $sectionB;

    private Period $period;

    private Subject $maths;

    private Employee $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
        $this->school = School::factory()->create();
        $this->school->menus()->attach(Menu::query()->whereIn('key', ['timetable', 'subjects'])->pluck('id'));
        $this->admin = User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => Role::factory()->platform()->allSchoolMenus()->create()->getKey(),
        ]);

        $schoolId = $this->school->getKey();
        $this->session = AcademicSession::query()->create(['school_id' => $schoolId, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true]);
        $class = SchoolClass::query()->create(['school_id' => $schoolId, 'name' => 'Class 5']);
        $this->sectionA = Section::query()->create(['school_id' => $schoolId, 'class_id' => $class->getKey(), 'name' => 'A']);
        $this->sectionB = Section::query()->create(['school_id' => $schoolId, 'class_id' => $class->getKey(), 'name' => 'B']);
        $this->period = Period::query()->create(['school_id' => $schoolId, 'name' => 'Period 1', 'starts_at' => '08:00', 'ends_at' => '08:40', 'sort_order' => 1]);
        $this->maths = Subject::query()->create(['school_id' => $schoolId, 'name' => 'Maths', 'type' => 'theory']);
        $this->teacher = Employee::query()->create(['school_id' => $schoolId, 'employee_no' => 'TCH-0001', 'type' => 'teacher', 'name' => 'Anita Rao']);
    }

    public function test_timetable_grid_is_saved_and_shown(): void
    {
        $this->save($this->sectionA, [1 => [$this->period->getKey() => ['subject_id' => $this->maths->getKey(), 'teacher_id' => $this->teacher->getKey()]]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('timetable_entries', [
            'academic_session_id' => $this->session->getKey(), 'section_id' => $this->sectionA->getKey(), 'day' => 1,
            'period_id' => $this->period->getKey(), 'subject_id' => $this->maths->getKey(), 'teacher_id' => $this->teacher->getKey(),
        ]);

        $this->actingAs($this->admin)->get(route('school.timetable.index', ['section_id' => $this->sectionA->getKey()]))
            ->assertOk()->assertSee('Anita Rao')->assertSee('Period 1');
    }

    public function test_teacher_cannot_be_in_two_sections_at_the_same_time(): void
    {
        $slot = [1 => [$this->period->getKey() => ['subject_id' => $this->maths->getKey(), 'teacher_id' => $this->teacher->getKey()]]];

        $this->save($this->sectionA, $slot)->assertSessionHasNoErrors();
        $this->save($this->sectionB, $slot)->assertSessionHasErrors('slots');

        $this->assertSame(0, TimetableEntry::query()->where('section_id', $this->sectionB->getKey())->count());
    }

    public function test_staff_and_other_school_ids_are_rejected(): void
    {
        $staff = Employee::query()->create(['school_id' => $this->school->getKey(), 'employee_no' => 'STF-0001', 'type' => 'staff', 'name' => 'Clerk']);
        $foreignSubject = Subject::query()->create(['school_id' => School::factory()->create()->getKey(), 'name' => 'Foreign', 'type' => 'theory']);

        $this->save($this->sectionA, [1 => [$this->period->getKey() => ['teacher_id' => $staff->getKey()]]])
            ->assertSessionHasErrors();
        $this->save($this->sectionA, [1 => [$this->period->getKey() => ['subject_id' => $foreignSubject->getKey()]]])
            ->assertSessionHasErrors();

        $this->assertSame(0, TimetableEntry::query()->count());
    }

    public function test_break_periods_never_receive_lessons(): void
    {
        $lunch = Period::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Lunch', 'is_break' => true, 'sort_order' => 2]);

        $this->save($this->sectionA, [1 => [$lunch->getKey() => ['subject_id' => $this->maths->getKey()]]])->assertSessionHasNoErrors();

        $this->assertSame(0, TimetableEntry::query()->count());
    }

    public function test_period_management_and_subject_in_use_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->post(route('school.periods.store'), ['_token' => 't', 'name' => 'Period 2', 'starts_at' => '08:40', 'ends_at' => '09:20', 'sort_order' => 2])
            ->assertRedirect(route('school.periods.index'));
        $this->assertDatabaseHas('periods', ['name' => 'Period 2', 'is_break' => false]);

        $this->save($this->sectionA, [2 => [$this->period->getKey() => ['subject_id' => $this->maths->getKey()]]]);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.subjects.destroy', $this->maths), ['_token' => 't'])
            ->assertSessionHas('error');
        $this->assertModelExists($this->maths);
    }

    /**
     * @param  array<int, array<int, array<string, int>>>  $slots
     */
    private function save(Section $section, array $slots): TestResponse
    {
        return $this->actingAs($this->admin)->withSession(['_token' => 't'])->put(route('school.timetable.update'), [
            '_token' => 't',
            'academic_session_id' => $this->session->getKey(),
            'section_id' => $section->getKey(),
            'slots' => $slots,
        ]);
    }
}
