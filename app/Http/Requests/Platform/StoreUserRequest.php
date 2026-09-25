<?php

namespace App\Http\Requests\Platform;

use App\Enums\UserStatus;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
            ...$this->accessRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function accessRules(): array
    {
        return [
            'school_id' => ['required', 'integer', Rule::exists('schools', 'id')],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'overrides' => ['sometimes', 'array'],
            'overrides.*' => ['array'],
            'overrides.*.*' => ['nullable', Rule::in(['allow', 'deny'])],
        ];
    }

    protected function passwordRule(): Password
    {
        return Password::min(12)->mixedCase()->letters()->numbers()->symbols();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $roleId = $this->input('role_id');

                if ($roleId === null || $validator->errors()->hasAny(['role_id', 'school_id'])) {
                    return;
                }

                $role = Role::query()->find((int) $roleId);

                if ($role === null || ! $role->isUsableIn((int) $this->input('school_id'))) {
                    $validator->errors()->add('role_id', 'Choose a platform role or a role that belongs to the selected school.');
                }
            },
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
        ]);
    }
}
