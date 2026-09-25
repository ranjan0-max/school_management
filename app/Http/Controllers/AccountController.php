<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('account.edit');
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->safe()->only(['name', 'email']);
        $emailChanged = $user->email !== $validated['email'];
        $oldValues = ['name' => $user->name, 'email' => $user->email];

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        Log::notice('Account profile updated.', [
            'actor_id' => $user->getKey(),
            'email_changed' => $emailChanged,
            'ip_address' => $request->ip(),
        ]);

        $this->audit->record(
            event: 'account.profile_updated',
            actor: $user,
            auditableType: $user::class,
            auditableId: $user->getKey(),
            oldValues: $oldValues,
            newValues: ['name' => $user->name, 'email' => $user->email],
            metadata: ['email_changed' => $emailChanged],
        );

        return back()->with('profile_status', 'Your account details were updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->session()->regenerate();

        Log::notice('Account password changed.', [
            'actor_id' => $user->getKey(),
            'ip_address' => $request->ip(),
        ]);

        $this->audit->record(
            event: 'account.password_changed',
            actor: $user,
            auditableType: $user::class,
            auditableId: $user->getKey(),
            metadata: ['other_database_sessions_revoked' => config('session.driver') === 'database'],
        );

        return back()->with('password_status', 'Your password was changed successfully.');
    }
}
