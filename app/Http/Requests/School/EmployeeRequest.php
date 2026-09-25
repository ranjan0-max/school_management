<?php

namespace App\Http\Requests\School;

use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Models\Employee;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the name is required; everything else can be completed later.
 */
class EmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');

        return [
            'employee_no' => [
                'nullable', 'string', 'max:50', 'alpha_dash:ascii',
                Rule::unique('employees', 'employee_no')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($employee instanceof Employee ? $employee->getKey() : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'designation' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['employee_no', 'gender', 'date_of_birth', 'phone', 'email', 'designation', 'qualification', 'joining_date', 'address'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        $this->merge([
            ...$values,
            'employee_no' => $values['employee_no'] === null ? null : strtoupper($values['employee_no']),
            'email' => $values['email'] === null ? null : strtolower($values['email']),
            'name' => trim((string) $this->input('name')),
            'status' => $this->input('status') ?: EmployeeStatus::Active->value,
        ]);
    }
}
