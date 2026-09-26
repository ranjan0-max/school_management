<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class NavigationSeeder extends Seeder
{
    private const CRUD = 'view,create,edit,delete';

    public function run(): void
    {
        // Dashboard is not listed: every signed-in user reaches it through the fixed sidebar link.
        $catalog = [
            ['key' => 'academic_management', 'name' => 'Academic Management', 'icon' => 'academic', 'pages' => [
                ['academic_sessions', 'Academic Sessions', self::CRUD, 'school.academic-sessions.index'],
                ['classes', 'Classes', self::CRUD, 'school.classes.index'],
                ['sections', 'Sections', self::CRUD, 'school.sections.index'],
                ['subjects', 'Subjects', self::CRUD, 'school.subjects.index'],
                ['timetable', 'Timetable', self::CRUD, 'school.timetable.index'],
            ]],
            ['key' => 'people_management', 'name' => 'People Management', 'icon' => 'people', 'pages' => [
                ['students', 'Students', 'view,create,edit,export', 'school.students.index'],
                // Guardians are created from the student form, so there is no "create" action.
                ['guardians', 'Guardians', 'view,edit,delete', 'school.guardians.index'],
                ['teachers', 'Teachers', 'view,create,edit', 'school.teachers.index'],
                ['staff', 'Staff', 'view,create,edit', 'school.staff.index'],
            ]],
            ['key' => 'attendance', 'name' => 'Attendance', 'icon' => 'attendance', 'pages' => [
                ['student_attendance', 'Student Attendance', 'view,create,edit,approve', 'school.student-attendance.index'],
                ['staff_attendance', 'Staff Attendance', 'view,create,edit,approve', 'school.staff-attendance.index'],
                ['holidays', 'Holidays', self::CRUD, 'school.holidays.index'],
                ['attendance_reports', 'Attendance Reports', 'view,export', 'school.attendance-reports.index'],
            ]],
            ['key' => 'fee_management', 'name' => 'Fee Management', 'icon' => 'fees', 'pages' => [
                ['fee_heads', 'Fee Heads', self::CRUD],
                ['fee_structures', 'Fee Structures', self::CRUD],
                ['fee_invoices', 'Fee Invoices', self::CRUD],
                ['fee_payments', 'Payments', 'view,create,approve'],
                ['fee_reports', 'Fee Reports', 'view,export'],
            ]],
            ['key' => 'examination', 'name' => 'Examination', 'icon' => 'examination', 'pages' => [
                ['exam_types', 'Exam Types', self::CRUD],
                ['exam_schedule', 'Exam Schedule', self::CRUD],
                ['marks_entry', 'Marks Entry', 'view,create,edit'],
                ['results', 'Results', 'view,approve'],
                ['report_cards', 'Report Cards', 'view,export'],
            ]],
            ['key' => 'communication', 'name' => 'Communication', 'icon' => 'communication', 'pages' => [
                ['notices', 'Notices', self::CRUD],
                ['messages', 'Messages', 'view,create,delete'],
                ['notifications', 'Notifications', 'view'],
            ]],
            ['key' => 'other_services', 'name' => 'Other Services', 'icon' => 'services', 'pages' => [
                ['library', 'Library', self::CRUD],
                ['transport', 'Transport', self::CRUD],
                ['hostel', 'Hostel', self::CRUD],
            ]],
            ['key' => 'administration', 'name' => 'Administration', 'icon' => 'settings', 'pages' => [
                ['users', 'Users', self::CRUD],
                ['roles', 'Roles & Access', self::CRUD],
                ['audit_logs', 'Audit Logs', 'view,export'],
                ['school_settings', 'School Settings', 'view,edit'],
            ]],
        ];

        $catalogKeys = [];

        foreach ($catalog as $groupOrder => $group) {
            $catalogKeys[] = $group['key'];
            $catalogKeys = [...$catalogKeys, ...array_column($group['pages'], 0)];

            $parent = Menu::query()->updateOrCreate(['key' => $group['key']], [
                'parent_id' => null,
                'name' => $group['name'],
                'icon' => $group['icon'],
                'actions' => null,
                'sort_order' => ($groupOrder + 1) * 10,
                'is_active' => true,
            ]);

            foreach ($group['pages'] as $pageOrder => $page) {
                [$key, $name, $actions] = $page;

                Menu::query()->updateOrCreate(['key' => $key], [
                    'parent_id' => $parent->getKey(),
                    'name' => $name,
                    'route_name' => $page[3] ?? null,
                    'actions' => $actions,
                    'sort_order' => ($pageOrder + 1) * 10,
                    'is_active' => true,
                ]);
            }
        }

        // Pages removed or renamed in the catalog (for example grade_levels → classes) are
        // deleted; their school_menus / role_menus / override rows cascade with them.
        Menu::query()->whereNotNull('parent_id')->whereNotIn('key', $catalogKeys)->delete();
        Menu::query()->whereNull('parent_id')->whereNotIn('key', $catalogKeys)->delete();
    }
}
