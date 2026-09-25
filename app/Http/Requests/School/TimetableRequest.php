<?php

namespace App\Http\Requests\School;

use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * slots[day][period_id][subject_id|teacher_id] — every id must belong to the current school.
 */
class TimetableRequest extends FormRequest
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

        return [
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId)],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'slots' => ['sometimes', 'array'],
            'slots.*' => ['array'],
            'slots.*.*' => ['array'],
            'slots.*.*.subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'slots.*.*.teacher_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')
                    ->where('school_id', $schoolId)
                    ->where('type', EmployeeType::Teacher->value)
                    ->where('status', EmployeeStatus::Active->value),
            ],
        ];
    }
}
