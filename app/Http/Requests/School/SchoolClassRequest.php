<?php

namespace App\Http\Requests\School;

use App\Models\SchoolClass;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
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
        $class = $this->route('schoolClass');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('classes', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($class instanceof SchoolClass ? $class->getKey() : null),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'subject_ids' => ['sometimes', 'array'],
            'subject_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
        ]);
    }
}
