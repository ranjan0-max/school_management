<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\SchoolSettingsRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * The school's own details and preferences. Status, trial, billing, limits and menus
 * are not here: only the Super Admin changes those.
 */
class SettingsController extends Controller implements HasMiddleware
{
    /** Carbon day numbers in the order a school week is read. */
    public const WEEK = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];

    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:school_settings', only: ['edit']),
            new Middleware('menu:school_settings,edit', only: ['update']),
        ];
    }

    public function edit(): View
    {
        return view('school.admin.settings.edit', [
            'school' => $this->currentSchool(),
            'week' => self::WEEK,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(SchoolSettingsRequest $request): RedirectResponse
    {
        $school = $this->currentSchool();
        $before = $this->auditValues($school->only(['name', 'email', 'phone', 'address', 'timezone']), $school->settings ?? []);
        $validated = $request->validated();

        $school->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'timezone' => $validated['timezone'],
            'settings' => $request->settings($school->settings ?? []),
        ]);

        $this->audit->record(
            event: 'school.settings_updated',
            actor: $request->user(),
            school: $school,
            auditableType: $school::class,
            auditableId: $school->getKey(),
            oldValues: $before,
            newValues: $this->auditValues($school->only(['name', 'email', 'phone', 'address', 'timezone']), $school->settings ?? []),
        );

        return redirect()->route('school.settings.edit')->with('status', 'School settings saved.');
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function auditValues(array $details, array $settings): array
    {
        return [...$details, 'settings' => array_intersect_key($settings, array_flip(SchoolSettingsRequest::SETTING_KEYS))];
    }
}
