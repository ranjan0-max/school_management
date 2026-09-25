<?php

namespace App\Http\Requests\School;

use App\Enums\SubjectType;
use App\Models\Subject;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
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
        $subject = $this->route('subject');
        $ignoreId = $subject instanceof Subject ? $subject->getKey() : null;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($ignoreId),
            ],
            'code' => [
                'nullable', 'string', 'max:30', 'alpha_dash:ascii',
                Rule::unique('subjects', 'code')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($ignoreId),
            ],
            'type' => ['required', Rule::enum(SubjectType::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $code === '' ? null : $code,
        ]);
    }
}
