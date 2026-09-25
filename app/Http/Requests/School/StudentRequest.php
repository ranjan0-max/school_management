<?php

namespace App\Http\Requests\School;

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the first name is required. Section, roll number and guardians are optional.
 */
class StudentRequest extends FormRequest
{
    public const MAX_GUARDIANS = 2;

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
        $student = $this->route('student');

        return [
            'admission_no' => [
                'nullable', 'string', 'max:50', 'alpha_dash:ascii',
                Rule::unique('students', 'admission_no')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($student instanceof Student ? $student->getKey() : null),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'admission_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(StudentStatus::class)],

            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'roll_no' => ['nullable', 'string', 'max:20'],

            'guardians' => ['sometimes', 'array', 'max:'.self::MAX_GUARDIANS],
            'guardians.*.name' => ['nullable', 'string', 'max:255', 'required_with:guardians.*.phone,guardians.*.email'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.relation' => ['nullable', Rule::enum(GuardianRelation::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guardians.*.name' => 'guardian name',
            'guardians.*.phone' => 'guardian phone',
            'guardians.*.email' => 'guardian email',
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['admission_no', 'last_name', 'gender', 'date_of_birth', 'blood_group', 'phone', 'email', 'address', 'admission_date', 'section_id', 'roll_no'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        $guardians = [];

        foreach (array_slice((array) $this->input('guardians', []), 0, self::MAX_GUARDIANS) as $guardian) {
            $guardian = is_array($guardian) ? $guardian : [];
            $guardians[] = [
                'name' => trim((string) ($guardian['name'] ?? '')) ?: null,
                'phone' => trim((string) ($guardian['phone'] ?? '')) ?: null,
                'email' => strtolower(trim((string) ($guardian['email'] ?? ''))) ?: null,
                'relation' => ($guardian['relation'] ?? '') ?: null,
            ];
        }

        $this->merge([
            ...$values,
            'admission_no' => $values['admission_no'] === null ? null : strtoupper($values['admission_no']),
            'blood_group' => $values['blood_group'] === null ? null : strtoupper($values['blood_group']),
            'email' => $values['email'] === null ? null : strtolower($values['email']),
            'first_name' => trim((string) $this->input('first_name')),
            'status' => $this->input('status') ?: StudentStatus::Active->value,
            'guardians' => $guardians,
        ]);
    }
}
