<?php

namespace App\Http\Requests\School;

use App\Enums\AttendanceStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentAttendanceRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'attendance' => ['required', 'array', 'max:500'],
            'attendance.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendance.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance.required' => 'There are no students to mark.',
            'attendance.*.status.required' => 'Choose a status for every student.',
        ];
    }
}
