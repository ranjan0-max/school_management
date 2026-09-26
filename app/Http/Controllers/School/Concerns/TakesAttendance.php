<?php

namespace App\Http\Controllers\School\Concerns;

use App\Enums\AttendanceSheetStatus;
use App\Models\Holiday;
use App\Models\StaffAttendanceSheet;
use App\Models\StudentAttendanceSheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Rules shared by student and staff attendance: dates, holidays, the approval lock.
 */
trait TakesAttendance
{
    /**
     * Today's date as the school sees it (its own timezone), as a plain date like every
     * other date here, so comparisons never shift by the timezone offset.
     */
    protected function today(): CarbonImmutable
    {
        $timezone = $this->currentSchool()->timezone ?: config('app.timezone');

        return CarbonImmutable::parse(CarbonImmutable::now($timezone)->toDateString());
    }

    /**
     * The ?date=Y-m-d asked for, or today when it is missing, invalid or in the future.
     */
    protected function requestedDate(Request $request): CarbonImmutable
    {
        $date = $this->parseDate($request->string('date')->toString());
        $today = $this->today();

        return $date === null || $date->greaterThan($today) ? $today : $date;
    }

    protected function parseDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Attendance cannot be taken for a future day or on a holiday.
     */
    protected function ensureMarkable(CarbonImmutable $date): void
    {
        if ($date->greaterThan($this->today())) {
            throw ValidationException::withMessages(['date' => 'Attendance cannot be taken for a future date.']);
        }

        $holiday = Holiday::coveringDay((int) $this->currentSchool()->getKey(), $date->toDateString());

        if ($holiday !== null) {
            throw ValidationException::withMessages(['date' => "{$date->format('d M Y')} is a holiday ({$holiday->name})."]);
        }
    }

    /**
     * A new sheet needs "create", changing one needs "edit", and an approved sheet is locked.
     */
    protected function ensureCanSave(Request $request, StudentAttendanceSheet|StaffAttendanceSheet|null $sheet, string $menuKey): void
    {
        if ($sheet?->isApproved()) {
            throw ValidationException::withMessages(['attendance' => 'This attendance is approved and locked. Reopen it to make changes.']);
        }

        abort_unless($request->user()?->can('menu', [$menuKey, $sheet === null ? 'create' : 'edit']), 403);
    }

    protected function approveSheet(Request $request, StudentAttendanceSheet|StaffAttendanceSheet $sheet, string $event): void
    {
        $this->ensureCurrentSchool($sheet);

        $sheet->update([
            'status' => AttendanceSheetStatus::Approved,
            'approved_by' => $request->user()?->getKey(),
            'approved_at' => now(),
        ]);

        $this->recordSheet($event, $request, $sheet);
    }

    protected function reopenSheet(Request $request, StudentAttendanceSheet|StaffAttendanceSheet $sheet, string $event): void
    {
        $this->ensureCurrentSchool($sheet);

        $sheet->update(['status' => AttendanceSheetStatus::Submitted, 'approved_by' => null, 'approved_at' => null]);

        $this->recordSheet($event, $request, $sheet);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function recordSheet(string $event, Request $request, StudentAttendanceSheet|StaffAttendanceSheet $sheet, array $extra = []): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: $sheet::class,
            auditableId: $sheet->getKey(),
            newValues: ['date' => $sheet->date->toDateString(), 'status' => $sheet->status->value, ...$extra],
        );
    }
}
