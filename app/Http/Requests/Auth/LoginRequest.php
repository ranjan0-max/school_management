<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt([
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'status' => UserStatus::Active->value,
        ], $this->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            $this->audit('auth.login.failed');

            throw ValidationException::withMessages([
                'email' => __('The provided credentials are incorrect or this account is inactive.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $this->audit('auth.login.locked_out', ['retry_after_seconds' => $seconds]);

        throw ValidationException::withMessages([
            'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    /**
     * Records the attempted email only; the password must never reach any log.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $event, array $metadata = []): void
    {
        $email = Str::lower($this->string('email')->toString());

        app(AuditLogger::class)->record(
            event: $event,
            actor: User::query()->where('email', $email)->first(),
            metadata: ['email' => $email, ...$metadata],
        );
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
