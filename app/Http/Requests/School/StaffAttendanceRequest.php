<?php

namespace App\Http\Requests\School;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffAttendanceRequest extends FormRequest
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
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'attendance' => ['required', 'array', 'max:500'],
            'attendance.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendance.*.check_in' => ['nullable', 'date_format:H:i'],
            'attendance.*.check_out' => ['nullable', 'date_format:H:i'],
            'attendance.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance.required' => 'There is nobody to mark.',
            'attendance.*.status.required' => 'Choose a status for every person.',
            'attendance.*.check_in.date_format' => 'Check-in must be a time like 08:30.',
            'attendance.*.check_out.date_format' => 'Check-out must be a time like 15:30.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('attendance');

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $id => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (['check_in', 'check_out'] as $field) {
                $rows[$id][$field] = filled($row[$field] ?? null) ? substr((string) $row[$field], 0, 5) : null;
            }
        }

        $this->merge(['attendance' => $rows]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('attendance') as $id => $row) {
                    $in = $row['check_in'] ?? null;
                    $out = $row['check_out'] ?? null;

                    if (is_string($in) && is_string($out) && $out < $in) {
                        $validator->errors()->add("attendance.{$id}.check_out", 'Check-out cannot be before check-in.');
                    }
                }
            },
        ];
    }
}
