@props(['name'])

@php
    $icon = match ((string) $name) {
        'dashboard' => '<path d="M4 4h6v7H4V4Zm10 0h6v4h-6V4ZM4 15h6v5H4v-5Zm10-3h6v8h-6v-8Z"/>',
        'schools' => '<path d="M4 20V6l8-3 8 3v14M8 9h2m4 0h2M8 13h2m4 0h2M9 20v-3h6v3"/>',
        'roles' => '<path d="M16 19h5v-1.5a3.5 3.5 0 0 0-5.2-3.05M16 19H8m8 0v-1.5c0-1.08-.38-2.07-1.02-2.84M8 19H3v-1.5a3.5 3.5 0 0 1 5.2-3.05M8 19v-1.5c0-1.08.38-2.07 1.02-2.84m5.96 0a4 4 0 0 0-5.96 0M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm5 2a2.5 2.5 0 1 1-4.03-1.98M4 10a2.5 2.5 0 1 0 4.03-1.98"/>',
        'users' => '<path d="M16 20v-1.5a4.5 4.5 0 0 0-9 0V20m12-8a3 3 0 0 1 0 6m-7-6a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/>',
        'audit_logs' => '<path d="M9 5h6m-7 3h8m-8 4h5m-7-9h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm9 12 1.5 1.5L19 14"/>',
        'academic_sessions' => '<path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 8h3v3H8v-3Z"/>',
        'classes' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v17H6.5A2.5 2.5 0 0 0 4 22V5.5ZM20 5.5A2.5 2.5 0 0 0 17.5 3H13v17h4.5A2.5 2.5 0 0 1 20 22V5.5Z"/>',
        'sections' => '<path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z"/>',
        'subjects' => '<path d="M6 4h11a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2V5a1 1 0 0 1 1-1Zm1 0v16m3-12h6m-6 4h6"/>',
        'timetable' => '<path d="M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
        'students' => '<path d="m3 9 9-5 9 5-9 5-9-5Zm4 3v4c2.7 2.2 7.3 2.2 10 0v-4m4-3v6"/>',
        'guardians' => '<path d="M12 21s8-3.8 8-10V5l-8-3-8 3v6c0 6.2 8 10 8 10Zm0-10a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm-4 5a4 4 0 0 1 8 0"/>',
        'teachers' => '<path d="M15 19H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7M8 9h8m-8 4h4m5 8v-6m-3 3h6"/>',
        'staff' => '<path d="M9 7V5a3 3 0 0 1 6 0v2m-9 0h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm-2 5h16M9 12v2h6v-2"/>',
        'student_attendance', 'staff_attendance', 'attendance_reports' => '<path d="M9 11l2 2 4-5m-8-5v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/>',
        'holidays' => '<path d="M12 3v2m0 14v2M5.64 5.64l1.41 1.41m9.9 9.9 1.41 1.41M3 12h2m14 0h2M5.64 18.36l1.41-1.41m9.9-9.9 1.41-1.41M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>',
        'fee_heads','fee_structures', 'fee_invoices', 'fee_payments', 'fee_reports' => '<path d="M12 3v18m4-14.5H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H7"/>',
        'exam_types', 'exam_schedule', 'marks_entry', 'results', 'report_cards' => '<path d="M5 3h14v18H5V3Zm4 5h6m-6 4h6m-6 4h3"/>',
        'notices', 'messages', 'notifications' => '<path d="M4 5h16v12H8l-4 4V5Zm4 4h8m-8 4h5"/>',
        'library' => '<path d="M4 5h5v15H4V5Zm6-2h5v17h-5V3Zm6 4h4v13h-4V7Z"/>',
        'transport' => '<path d="M5 16h14m-13 0-2-3V8a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v5l-2 3M7 19h.01M17 19h.01M4 11h16"/>',
        'hostel' => '<path d="M4 21V8l8-5 8 5v13M8 11h3v3H8v-3Zm5 0h3v3h-3v-3Zm-4 10v-4h6v4"/>',
        'school_settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-3.5 2-1-2-3-2.2.4a7 7 0 0 0-1.2-.7L15 5.5h-6l-.6 2.2a7 7 0 0 0-1.2.7L5 8l-2 3 2 1v1l-2 1 2 3 2.2-.4c.4.3.8.5 1.2.7L9 19.5h6l.6-2.2c.4-.2.8-.4 1.2-.7l2.2.4 2-3-2-1v-1Z"/>',
        default => '<path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 4h8m-8 4h8m-8 4h5"/>',
    };
@endphp

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    {!! $icon !!}
</svg>
