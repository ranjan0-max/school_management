<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\GuardianRequest;
use App\Models\Guardian;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class GuardianController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:guardians', only: ['index']),
            new Middleware('menu:guardians,edit', only: ['edit', 'update']),
            new Middleware('menu:guardians,delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);

        $guardians = Guardian::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->withCount('students')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('school.guardians.index', [
            'school' => $this->currentSchool(),
            'guardians' => $guardians,
            'search' => $search,
        ]);
    }

    public function edit(Guardian $guardian): View
    {
        $this->ensureCurrentSchool($guardian);

        return view('school.guardians.form', [
            'school' => $this->currentSchool(),
            'guardian' => $guardian,
            'students' => $guardian->students()->orderBy('first_name')->get(),
        ]);
    }

    public function update(GuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $this->ensureCurrentSchool($guardian);

        $guardian->update($request->validated());
        $this->record('guardian.updated', $request, $guardian);

        return redirect()->route('school.guardians.index')->with('status', "Guardian {$guardian->name} updated.");
    }

    public function destroy(Request $request, Guardian $guardian): RedirectResponse
    {
        $this->ensureCurrentSchool($guardian);

        if ($guardian->students()->exists()) {
            return back()->with('error', "{$guardian->name} is linked to students. Remove the link from the student first.");
        }

        $guardian->delete();
        $this->record('guardian.deleted', $request, $guardian);

        return back()->with('status', 'Guardian deleted.');
    }

    private function record(string $event, Request $request, Guardian $guardian): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Guardian::class,
            auditableId: $guardian->getKey(),
            // Contact details are personal data; the audit keeps only the name.
            newValues: ['name' => $guardian->name],
        );
    }
}
