<?php

namespace App\Http\Requests\School;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * A school user created or edited by the school's own administrator. Only the school's
 * active roles can be given, and only Active / Inactive statuses are offered.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->requireSchool()->getKey();
        $user = $this->route('user');
        $isEditing = $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($isEditing ? $user->getKey() : null),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$isEditing ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            'role_id' => [
                'nullable', 'integer',
                // Keeping a role that was deactivated later is allowed; choosing one is not.
                Rule::exists('roles', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('school_id', $schoolId)
                    ->where('type', RoleType::School->value)
                    ->where(fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->orWhere('id', $isEditing ? (int) $user->role_id : 0))),
            ],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
            'overrides' => ['sometimes', 'array'],
            'overrides.*' => ['array'],
            'overrides.*.*' => ['nullable', Rule::in(['allow', 'deny'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_id.exists' => 'Choose one of this school\'s active roles.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $phone === '' ? null : $phone,
            'role_id' => $this->filled('role_id') ? $this->input('role_id') : null,
            'status' => $this->input('status', UserStatus::Active->value),
        ]);
    }
}
