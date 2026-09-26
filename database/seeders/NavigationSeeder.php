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
                // Notices are archived (an "edit"), never deleted. Messages and notifications
                // were dropped from the plan on 2026-09-27.
                ['notices', 'Notices', 'view,create,edit', 'school.notices.index'],
            ]],
            ['key' => 'other_services', 'name' => 'Other Services', 'icon' => 'services', 'pages' => [
                ['library', 'Library', self::CRUD],
                ['transport', 'Transport', self::CRUD],
                ['hostel', 'Hostel', self::CRUD],
            ]],
            ['key' => 'administration', 'name' => 'Administration', 'icon' => 'settings', 'pages' => [
                // Users and roles are deactivated, never deleted, so there is no "delete" action.
                ['users', 'Users', 'view,create,edit', 'school.users.index'],
                ['roles', 'Roles & Access', 'view,create,edit', 'school.roles.index'],
                ['audit_logs', 'Audit Logs', 'view,export', 'school.audit-logs.index'],
                ['school_settings', 'School Settings', 'view,edit', 'school.settings.edit'],
            ]],
        ];

        $catalogKeys = [];

        // The order is set by the Super Admin (Platform → Menu order), so re-seeding never
        // touches sort_order of existing menus: new ones are added at the end of their group.
        foreach ($catalog as $group) {
            $catalogKeys[] = $group['key'];
            $catalogKeys = [...$catalogKeys, ...array_column($group['pages'], 0)];

            $parent = $this->place(Menu::query()->firstOrNew(['key' => $group['key']]), null, [
                'name' => $group['name'],
                'icon' => $group['icon'],
                'actions' => null,
            ]);

            foreach ($group['pages'] as $page) {
                [$key, $name, $actions] = $page;

                $this->place(Menu::query()->firstOrNew(['key' => $key]), (int) $parent->getKey(), [
                    'name' => $name,
                    'route_name' => $page[3] ?? null,
                    'actions' => $actions,
                ]);
            }
        }

        // Pages removed or renamed in the catalog (for example grade_levels → classes) are
        // deleted; their school_menus / role_menus / override rows cascade with them.
        Menu::query()->whereNotNull('parent_id')->whereNotIn('key', $catalogKeys)->delete();
        Menu::query()->whereNull('parent_id')->whereNotIn('key', $catalogKeys)->delete();
    }

    /**
     * Saves a menu under $parentId. A new menu, or one moved to another group, goes last.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function place(Menu $menu, ?int $parentId, array $attributes): Menu
    {
        $currentParentId = $menu->parent_id === null ? null : (int) $menu->parent_id;
        $movesGroup = ! $menu->exists || $currentParentId !== $parentId;

        $menu->fill([...$attributes, 'parent_id' => $parentId, 'is_active' => true]);

        if ($movesGroup) {
            $menu->sort_order = (int) Menu::query()
                ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'), fn ($query) => $query->where('parent_id', $parentId))
                ->whereKeyNot($menu->getKey() ?? 0)
                ->max('sort_order') + 10;
        }

        $menu->save();

        return $menu;
    }
}
