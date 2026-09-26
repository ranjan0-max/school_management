<?php

namespace App\Http\Requests\School;

use App\Enums\RoleType;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A school's own role. Names are unique inside the school and may not copy a platform role.
 */
class RoleRequest extends FormRequest
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
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($role instanceof Role ? $role->getKey() : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'all_school_menus' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'menus' => ['sometimes', 'array'],
            'menus.*' => ['array'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $copiesPlatformRole = Role::query()
                    ->where('type', RoleType::Platform)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $this->input('name'))])
                    ->exists();

                if ($copiesPlatformRole) {
                    $validator->errors()->add('name', 'This name is used by a platform role. Choose a different name.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = trim((string) $this->input('description'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => $description === '' ? null : $description,
            'all_school_menus' => $this->boolean('all_school_menus'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
