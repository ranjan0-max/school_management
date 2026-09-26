<?php

namespace Tests\Feature\School;

use App\Enums\AttendanceSheetStatus;
use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Menu;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StaffAttendanceSheet;
use App\Models\Student;
use App\Models\StudentAttendanceSheet;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['student_attendance', 'staff_attendance', 'holidays', 'attendance_reports'];

    private School $school;

    private User $admin;

    private AcademicSession $session;

    private Section $section;

    private Student $riya;

    private Student $aarav;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-15 06:00:00');
        $this->seed(NavigationSeeder::class);

        $this->school = School::factory()->create(['timezone' => 'Asia/Kolkata']);
        $this->school->menus()->attach(Menu::query()->whereIn('key', self::PAGES)->pluck('id'));
        $this->admin = User::factory()->create([
            'school_id' => $this->school->getKey(),
            'role_id' => Role::factory()->platform()->allSchoolMenus()->create()->getKey(),
        ]);

        $schoolId = $this->school->getKey();
        $this->session = AcademicSession::query()->create(['school_id' => $schoolId, 'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true]);
        $class = SchoolClass::query()->create(['school_id' => $schoolId, 'name' => 'Class 5']);
        $this->section = Section::query()->create(['school_id' => $schoolId, 'class_id' => $class->getKey(), 'name' => 'A']);
        $this->riya = $this->enrol('Riya', '1');
        $this->aarav = $this->enrol('Aarav', '2');
    }

    public function test_student_attendance_is_saved_shown_and_edited(): void
    {
        $outsider = Student::query()->create(['school_id' => $this->school->getKey(), 'admission_no' => 'ADM-9', 'first_name' => 'Outsider']);

        $this->saveStudents('2026-09-14', [
            $this->riya->getKey() => ['status' => 'present'],
            $this->aarav->getKey() => ['status' => 'absent', 'remark' => 'Fever'],
            $outsider->getKey() => ['status' => 'present'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $sheet = StudentAttendanceSheet::query()->sole();
        $this->assertSame(AttendanceSheetStatus::Submitted, $sheet->status);
        $this->assertSame($this->admin->getKey(), $sheet->taken_by);
        $this->assertSame(2, $sheet->records()->count());
        $this->assertDatabaseHas('student_attendances', ['student_id' => $this->aarav->getKey(), 'status' => 'absent', 'remark' => 'Fever']);
        $this->assertDatabaseMissing('student_attendances', ['student_id' => $outsider->getKey()]);

        $this->actingAs($this->admin)->get(route('school.student-attendance.index', ['section_id' => $this->section->getKey(), 'date' => '2026-09-14']))
            ->assertOk()->assertSee('Awaiting approval')->assertSee('Fever');

        $this->saveStudents('2026-09-14', [$this->aarav->getKey() => ['status' => 'late']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_attendances', ['student_id' => $this->aarav->getKey(), 'status' => 'late', 'remark' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student_attendance.updated']);
    }

    public function test_approved_attendance_is_locked_until_reopened(): void
    {
        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'present']]);
        $sheet = StudentAttendanceSheet::query()->sole();

        $this->patchAs(route('school.student-attendance.approve', $sheet))->assertRedirect();
        $this->assertTrue($sheet->refresh()->isApproved());
        $this->assertSame($this->admin->getKey(), $sheet->approved_by);

        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'absent']])->assertSessionHasErrors('attendance');
        $this->assertDatabaseHas('student_attendances', ['student_id' => $this->riya->getKey(), 'status' => 'present']);

        $this->patchAs(route('school.student-attendance.reopen', $sheet));
        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'absent']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_attendances', ['student_id' => $this->riya->getKey(), 'status' => 'absent']);
    }

    public function test_create_edit_and_approve_permissions_are_separate(): void
    {
        $teacher = $this->userWith(['student_attendance' => ['can_view' => true, 'can_create' => true]]);

        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'present']], $teacher)->assertSessionHasNoErrors();
        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'absent']], $teacher)->assertForbidden();

        $sheet = StudentAttendanceSheet::query()->sole();
        $this->patchAs(route('school.student-attendance.approve', $sheet), $teacher)->assertForbidden();

        $viewer = $this->userWith(['student_attendance' => ['can_view' => true]]);
        $this->saveStudents('2026-09-15', [$this->riya->getKey() => ['status' => 'present']], $viewer)->assertForbidden();
        $this->actingAs($viewer)->get(route('school.student-attendance.index', ['section_id' => $this->section->getKey(), 'date' => '2026-09-14']))
            ->assertOk()->assertDontSee('Save changes')->assertDontSee('Approve &amp; lock', false);
    }

    public function test_holidays_future_dates_and_dates_outside_the_session_cannot_be_marked(): void
    {
        Holiday::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Dussehra', 'starts_on' => '2026-09-10', 'ends_on' => '2026-09-12']);

        $this->saveStudents('2026-09-11', [$this->riya->getKey() => ['status' => 'present']])->assertSessionHasErrors('date');
        $this->saveStudents('2026-09-16', [$this->riya->getKey() => ['status' => 'present']])->assertSessionHasErrors('date');
        $this->saveStudents('2026-03-31', [$this->riya->getKey() => ['status' => 'present']])->assertSessionHasErrors('date');
        $this->assertDatabaseCount('student_attendance_sheets', 0);

        $this->actingAs($this->admin)->get(route('school.student-attendance.index', ['section_id' => $this->section->getKey(), 'date' => '2026-09-12']))
            ->assertOk()->assertSee('Holiday: Dussehra');
    }

    public function test_sheet_list_filters_by_status_and_hides_other_schools(): void
    {
        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'present']]);
        $this->saveStudents('2026-09-15', [$this->riya->getKey() => ['status' => 'absent']]);
        StudentAttendanceSheet::query()->whereDate('date', '2026-09-14')->sole()->update(['status' => AttendanceSheetStatus::Approved]);

        $this->actingAs($this->admin)->get(route('school.student-attendance.index', ['status' => 'submitted']))
            ->assertOk()->assertSee('Tue, 15 Sep 2026')->assertDontSee('Mon, 14 Sep 2026');

        $foreignSheet = StudentAttendanceSheet::query()->whereDate('date', '2026-09-15')->sole();
        $stranger = User::factory()->create([
            'school_id' => School::factory()->create()->getKey(),
            'role_id' => Role::factory()->platform()->allSchoolMenus()->create()->getKey(),
        ]);
        $stranger->school->menus()->attach(Menu::query()->whereIn('key', self::PAGES)->pluck('id'));

        $this->patchAs(route('school.student-attendance.approve', $foreignSheet), $stranger)->assertNotFound();
    }

    public function test_staff_attendance_saves_the_posted_page_with_times(): void
    {
        $anita = $this->employee('Anita Rao', 'teacher');
        $mohan = $this->employee('Mohan Lal', 'staff');

        $this->saveStaff('2026-09-14', [$anita->getKey() => ['status' => 'present', 'check_in' => '08:05', 'check_out' => '14:30']])
            ->assertSessionHasNoErrors();
        $this->saveStaff('2026-09-14', [$mohan->getKey() => ['status' => 'leave']])->assertSessionHasNoErrors();

        $sheet = StaffAttendanceSheet::query()->sole();
        $this->assertSame(2, $sheet->records()->count());
        $this->assertDatabaseHas('staff_attendances', ['employee_id' => $anita->getKey(), 'status' => 'present']);
        $this->assertSame('08:05', substr((string) $sheet->records()->where('employee_id', $anita->getKey())->value('check_in'), 0, 5));

        $this->saveStaff('2026-09-14', [$anita->getKey() => ['status' => 'present', 'check_in' => '14:00', 'check_out' => '08:00']])
            ->assertSessionHasErrors("attendance.{$anita->getKey()}.check_out");

        $this->actingAs($this->admin)->get(route('school.staff-attendance.index', ['date' => '2026-09-14', 'type' => 'teacher']))
            ->assertOk()->assertSee('Anita Rao')->assertDontSee('Mohan Lal')->assertSee('2 marked');
    }

    public function test_holidays_are_managed_and_filtered(): void
    {
        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->post(route('school.holidays.store'), ['_token' => 't', 'name' => 'Diwali', 'starts_on' => '2026-11-08', 'ends_on' => '2026-11-08'])
            ->assertRedirect(route('school.holidays.index'));

        $diwali = Holiday::query()->sole();
        $this->assertNull($diwali->ends_on);

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->post(route('school.holidays.store'), ['_token' => 't', 'name' => 'Winter break', 'starts_on' => '2026-12-28', 'ends_on' => '2026-12-20'])
            ->assertSessionHasErrors('ends_on');

        Holiday::query()->create(['school_id' => $this->school->getKey(), 'name' => 'New Year 2025', 'starts_on' => '2025-01-01']);

        $this->actingAs($this->admin)->get(route('school.holidays.index', ['year' => 2026]))
            ->assertOk()->assertSee('Diwali')->assertDontSee('New Year 2025');
        $this->actingAs($this->admin)->get(route('school.holidays.index', ['search' => 'new year']))
            ->assertOk()->assertSee('New Year 2025')->assertDontSee('Diwali');

        $this->actingAs($this->admin)->withSession(['_token' => 't'])
            ->delete(route('school.holidays.destroy', $diwali), ['_token' => 't']);
        $this->assertModelMissing($diwali);
    }

    public function test_monthly_report_shows_the_register_and_exports_csv(): void
    {
        Holiday::query()->create(['school_id' => $this->school->getKey(), 'name' => 'Onam', 'starts_on' => '2026-09-04']);
        $this->saveStudents('2026-09-14', [$this->riya->getKey() => ['status' => 'present'], $this->aarav->getKey() => ['status' => 'absent']]);
        $this->saveStudents('2026-09-15', [$this->riya->getKey() => ['status' => 'half_day'], $this->aarav->getKey() => ['status' => 'present']]);

        $filters = ['section_id' => $this->section->getKey(), 'month' => '2026-09'];

        $this->actingAs($this->admin)->get(route('school.attendance-reports.index', $filters))
            ->assertOk()
            ->assertSee('Riya')
            ->assertSee('75')
            ->assertSee('50')
            ->assertSee('2</strong> day(s) attendance taken', false)
            ->assertSee('title="Onam"', false);

        $csv = $this->actingAs($this->admin)->get(route('school.attendance-reports.export', $filters))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', preg_split('/\R/', trim(ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertSame(['Roll no', 'Admission no', 'Student'], array_slice($lines[0], 0, 3));
        $this->assertSame('Attendance %', end($lines[0]));
        $this->assertSame('Riya', $lines[1][2]);
        $this->assertSame('H', $lines[1][3 + 3]);
        $this->assertSame('P', $lines[1][3 + 13]);
        $this->assertSame('HD', $lines[1][3 + 14]);
        $this->assertSame('75', end($lines[1]));

        $viewer = $this->userWith(['attendance_reports' => ['can_view' => true]]);
        $this->actingAs($viewer)->get(route('school.attendance-reports.index', $filters))->assertOk()->assertDontSee('Export CSV');
        $this->actingAs($viewer)->get(route('school.attendance-reports.export', $filters))->assertForbidden();
    }

    public function test_staff_report_filters_and_paginates_on_the_server(): void
    {
        foreach (range(1, 51) as $number) {
            $this->employee(sprintf('Teacher %02d', $number), 'teacher');
        }
        $this->employee('Zara Office', 'staff');

        $this->actingAs($this->admin)->get(route('school.attendance-reports.staff', ['month' => '2026-09', 'type' => 'teacher']))
            ->assertOk()->assertSee('Teacher 50')->assertDontSee('Teacher 51')->assertDontSee('Zara Office')->assertSee('51 people');

        $this->actingAs($this->admin)->get(route('school.attendance-reports.staff', ['month' => '2026-09', 'search' => 'zara']))
            ->assertOk()->assertSee('Zara Office')->assertDontSee('Teacher 01');
    }

    public function test_sidebar_lists_the_attendance_pages(): void
    {
        $this->actingAs($this->admin)->get(route('school.dashboard'))
            ->assertOk()
            ->assertSee(route('school.student-attendance.index'))
            ->assertSee(route('school.staff-attendance.index'))
            ->assertSee(route('school.holidays.index'))
            ->assertSee(route('school.attendance-reports.index'));
    }

    public function test_sidebar_group_of_the_open_page_starts_expanded(): void
    {
        $html = $this->actingAs($this->admin)->get(route('school.holidays.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="dashboard-nav-group "\s+data-nav-group="nav-group-attendance"\s+data-nav-group-active/', $html);
        $this->assertStringContainsString('aria-expanded="true" aria-controls="nav-group-attendance"', $html);

        $html = $this->actingAs($this->admin)->get(route('school.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('class="dashboard-nav-group is-collapsed" data-nav-group="nav-group-attendance"', $html);
        $this->assertStringContainsString('aria-expanded="false" aria-controls="nav-group-attendance"', $html);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function saveStudents(string $date, array $rows, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)->withSession(['_token' => 't'])->put(route('school.student-attendance.save'), [
            '_token' => 't',
            'academic_session_id' => $this->session->getKey(),
            'section_id' => $this->section->getKey(),
            'date' => $date,
            'attendance' => $rows,
        ]);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function saveStaff(string $date, array $rows): TestResponse
    {
        return $this->actingAs($this->admin)->withSession(['_token' => 't'])->put(route('school.staff-attendance.save'), [
            '_token' => 't',
            'date' => $date,
            'attendance' => $rows,
        ]);
    }

    private function patchAs(string $url, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)->withSession(['_token' => 't'])->patch($url, ['_token' => 't']);
    }

    private function enrol(string $name, string $roll): Student
    {
        $student = Student::query()->create(['school_id' => $this->school->getKey(), 'admission_no' => 'ADM-'.$roll, 'first_name' => $name]);
        Enrollment::query()->create([
            'school_id' => $this->school->getKey(),
            'student_id' => $student->getKey(),
            'academic_session_id' => $this->session->getKey(),
            'section_id' => $this->section->getKey(),
            'roll_no' => $roll,
        ]);

        return $student;
    }

    private function employee(string $name, string $type): Employee
    {
        static $number = 0;

        return Employee::query()->create(['school_id' => $this->school->getKey(), 'employee_no' => 'EMP-'.(++$number), 'type' => $type, 'name' => $name]);
    }

    /**
     * @param  array<string, array<string, bool>>  $grants
     */
    private function userWith(array $grants): User
    {
        $role = Role::factory()->create(['school_id' => $this->school->getKey()]);

        foreach ($grants as $key => $columns) {
            $role->menus()->attach(Menu::query()->where('key', $key)->value('id'), $columns);
        }

        return User::factory()->create(['school_id' => $this->school->getKey(), 'role_id' => $role->getKey()]);
    }
}
