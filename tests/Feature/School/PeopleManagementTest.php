<?php

namespace Tests\Feature\School;

use App\Enums\EmployeeType;
use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PeopleManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NavigationSeeder::class);
        $this->school = School::factory()->create();
        $this->school->menus()->attach(Menu::query()->whereIn('key', ['teachers', 'staff', 'guardians', 'students', 'sections'])->pluck('id'));
        $this->admin = User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => Role::factory()->platform()->allSchoolMenus()->create()->getKey(),
        ]);
    }

    public function test_employee_number_is_generated_when_left_empty_and_kept_when_given(): void
    {
        $this->postAs(route('school.teachers.store'), ['name' => 'Anita Rao'])->assertRedirect(route('school.teachers.index'));
        $this->postAs(route('school.teachers.store'), ['name' => 'Vikram Singh'])->assertRedirect();
        $this->postAs(route('school.teachers.store'), ['name' => 'Custom', 'employee_no' => 't-100'])->assertRedirect();
        $this->postAs(route('school.staff.store'), ['name' => 'Office Clerk'])->assertRedirect(route('school.staff.index'));

        $this->assertDatabaseHas('employees', ['name' => 'Anita Rao', 'employee_no' => 'TCH-0001', 'type' => 'teacher', 'status' => 'active']);
        $this->assertDatabaseHas('employees', ['name' => 'Vikram Singh', 'employee_no' => 'TCH-0002']);
        $this->assertDatabaseHas('employees', ['name' => 'Custom', 'employee_no' => 'T-100']);
        $this->assertDatabaseHas('employees', ['name' => 'Office Clerk', 'employee_no' => 'STF-0001', 'type' => 'staff']);
    }

    public function test_teacher_and_staff_lists_are_separate_and_protected_by_their_own_menu(): void
    {
        $teacher = $this->employee(EmployeeType::Teacher, 'Teacher One');
        $staff = $this->employee(EmployeeType::Staff, 'Staff One');

        $this->actingAs($this->admin)->get(route('school.teachers.index'))->assertOk()->assertSee('Teacher One')->assertDontSee('Staff One');
        $this->actingAs($this->admin)->get(route('school.staff.edit', $teacher))->assertNotFound();
        $this->actingAs($this->admin)->get(route('school.teachers.edit', $teacher))->assertOk();

        $teachersOnly = User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => $this->roleWith(['teachers' => ['can_view' => true]])->getKey(),
        ]);

        $this->actingAs($teachersOnly)->get(route('school.teachers.index'))->assertOk();
        $this->actingAs($teachersOnly)->get(route('school.staff.index'))->assertForbidden();
        $this->actingAs($teachersOnly)->get(route('school.teachers.edit', $teacher))->assertForbidden();
        $this->assertModelExists($staff);
    }

    public function test_other_schools_employees_are_not_reachable(): void
    {
        $foreign = Employee::query()->create([
            'school_id' => School::factory()->create()->getKey(), 'employee_no' => 'TCH-0001', 'type' => 'teacher', 'name' => 'Foreign Teacher',
        ]);

        $this->actingAs($this->admin)->get(route('school.teachers.index'))->assertDontSee('Foreign Teacher');
        $this->actingAs($this->admin)->get(route('school.teachers.edit', $foreign))->assertNotFound();
    }

    public function test_guardian_with_students_cannot_be_deleted(): void
    {
        $student = $this->student('Riya');
        $guardian = Guardian::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Meena']);
        $student->guardians()->attach($guardian, ['school_id' => $this->school->getKey()]);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.guardians.destroy', $guardian), ['_token' => 't'])
            ->assertSessionHas('error');

        $this->assertModelExists($guardian);
    }

    public function test_student_is_created_with_generated_number_enrollment_and_shared_guardian(): void
    {
        $session = AcademicSession::query()->create([
            'school_id' => $this->school->getKey(), 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true,
        ]);
        $section = $this->section('Class 5', 'A');

        $this->postAs(route('school.students.store'), [
            'first_name' => 'Aarav',
            'admission_date' => '2026-04-05',
            'section_id' => $section->getKey(),
            'roll_no' => '1',
            'guardians' => [['name' => 'Suresh Kumar', 'phone' => '9876543210', 'relation' => 'father'], ['name' => '']],
        ])->assertRedirect(route('school.students.index'));

        $this->postAs(route('school.students.store'), [
            'first_name' => 'Anaya',
            'admission_date' => '2026-04-06',
            'guardians' => [['name' => 'Suresh K.', 'phone' => '9876543210', 'relation' => 'father']],
        ])->assertRedirect();

        $aarav = Student::query()->where('first_name', 'Aarav')->sole();
        $anaya = Student::query()->where('first_name', 'Anaya')->sole();

        $this->assertSame('ADM-2026-0001', $aarav->admission_no);
        $this->assertSame('ADM-2026-0002', $anaya->admission_no);
        $this->assertDatabaseHas('enrollments', ['student_id' => $aarav->getKey(), 'academic_session_id' => $session->getKey(), 'section_id' => $section->getKey(), 'roll_no' => '1']);
        $this->assertSame(1, Guardian::query()->where('phone', '9876543210')->count());
        $this->assertSame(2, Guardian::query()->where('phone', '9876543210')->sole()->students()->count());
        $this->assertTrue((bool) $aarav->guardians()->sole()->pivot->is_primary);
    }

    public function test_roll_number_must_be_unique_within_the_section(): void
    {
        AcademicSession::query()->create([
            'school_id' => $this->school->getKey(), 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true,
        ]);
        $section = $this->section('Class 5', 'A');

        $this->postAs(route('school.students.store'), ['first_name' => 'One', 'section_id' => $section->getKey(), 'roll_no' => '7'])->assertSessionHasNoErrors();
        $this->postAs(route('school.students.store'), ['first_name' => 'Two', 'section_id' => $section->getKey(), 'roll_no' => '7'])->assertSessionHasErrors('roll_no');
    }

    public function test_student_list_filters_by_section_and_status_and_paginates(): void
    {
        $session = AcademicSession::query()->create([
            'school_id' => $this->school->getKey(), 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true,
        ]);
        $sectionA = $this->section('Class 5', 'A');
        $sectionB = Section::query()->create(['school_id' => $this->school->getKey(), 'class_id' => $sectionA->class_id, 'name' => 'B']);

        foreach (range(1, 21) as $number) {
            $this->student("Student {$number}")->enrollments()->create([
                'school_id' => $this->school->getKey(), 'academic_session_id' => $session->getKey(), 'section_id' => $sectionA->getKey(),
            ]);
        }
        $this->student('Beta Kid')->enrollments()->create([
            'school_id' => $this->school->getKey(), 'academic_session_id' => $session->getKey(), 'section_id' => $sectionB->getKey(),
        ]);
        $this->student('Old Pupil', 'left');

        $this->actingAs($this->admin)->get(route('school.students.index'))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->count() === 20 && $students->total() === 23);
        $this->actingAs($this->admin)->get(route('school.students.index', ['section_id' => $sectionB->getKey()]))
            ->assertViewHas('students', fn ($students) => $students->total() === 1)->assertSee('Beta Kid');
        $this->actingAs($this->admin)->get(route('school.students.index', ['status' => 'left']))
            ->assertViewHas('students', fn ($students) => $students->total() === 1);
    }

    public function test_section_with_students_cannot_be_deleted(): void
    {
        $session = AcademicSession::query()->create([
            'school_id' => $this->school->getKey(), 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true,
        ]);
        $section = $this->section('Class 5', 'A');
        $this->student('Placed')->enrollments()->create([
            'school_id' => $this->school->getKey(), 'academic_session_id' => $session->getKey(), 'section_id' => $section->getKey(),
        ]);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.sections.destroy', $section), ['_token' => 't'])
            ->assertSessionHas('error');

        $this->assertModelExists($section);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postAs(string $url, array $data): TestResponse
    {
        return $this->actingAs($this->admin)->withSession(['_token' => 't'])->post($url, ['_token' => 't', ...$data]);
    }

    private function employee(EmployeeType $type, string $name): Employee
    {
        return Employee::query()->create([
            'school_id' => $this->school->getKey(), 'employee_no' => $type->numberPrefix().'-'.fake()->unique()->numerify('####'),
            'type' => $type, 'name' => $name,
        ]);
    }

    private function student(string $firstName, string $status = 'active'): Student
    {
        return Student::query()->create([
            'school_id' => $this->school->getKey(), 'admission_no' => 'ADM-'.fake()->unique()->numerify('#####'),
            'first_name' => $firstName, 'status' => $status,
        ]);
    }

    private function section(string $className, string $name): Section
    {
        $class = SchoolClass::query()->firstOrCreate(['school_id' => $this->school->getKey(), 'name' => $className]);

        return Section::query()->create(['school_id' => $this->school->getKey(), 'class_id' => $class->getKey(), 'name' => $name]);
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
}
