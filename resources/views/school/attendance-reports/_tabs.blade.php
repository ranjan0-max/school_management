<nav class="attendance-report-tabs" aria-label="Attendance reports">
    <a class="{{ request()->routeIs('school.attendance-reports.index') ? 'active' : '' }}" href="{{ route('school.attendance-reports.index', ['month' => $month->format('Y-m')]) }}">Students</a>
    <a class="{{ request()->routeIs('school.attendance-reports.staff') ? 'active' : '' }}" href="{{ route('school.attendance-reports.staff', ['month' => $month->format('Y-m')]) }}">Teachers &amp; staff</a>
</nav>
