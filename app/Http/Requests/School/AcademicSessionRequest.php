<?php

namespace App\Http\Requests\School;

use App\Models\AcademicSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicSessionRequest extends FormRequest
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
        $session = $this->route('academic_session');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('academic_sessions', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($session instanceof AcademicSession ? $session->getKey() : null),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'is_current' => $this->boolean('is_current'),
        ]);
    }
}
