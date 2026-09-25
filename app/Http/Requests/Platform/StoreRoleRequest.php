<?php

namespace App\Http\Requests\Platform;

use App\Enums\RoleType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
        $isSchoolRole = $this->input('type') === RoleType::School->value;
        $schoolId = $isSchoolRole ? (int) $this->input('school_id') : null;

        return [
            'type' => ['required', Rule::enum(RoleType::class)],
            'school_id' => $isSchoolRole
                ? ['required', 'integer', Rule::exists('schools', 'id')]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($schoolId)],
            ...$this->sharedRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:2000'],
            'all_school_menus' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'menus' => ['sometimes', 'array'],
            'menus.*' => ['array'],
        ];
    }

    /**
     * Names are unique within a school, and unique among platform roles.
     */
    protected function uniqueNameRule(?int $schoolId, ?int $ignoreRoleId = null): mixed
    {
        return Rule::unique('roles', 'name')
            ->where(fn (Builder $query): Builder => $schoolId === null
                ? $query->whereNull('school_id')
                : $query->where('school_id', $schoolId))
            ->ignore($ignoreRoleId);
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
