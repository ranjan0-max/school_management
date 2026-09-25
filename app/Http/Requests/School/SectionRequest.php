<?php

namespace App\Http\Requests\School;

use App\Models\Section;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
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
        $section = $this->route('section');

        return [
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('class_id', (int) $this->input('class_id')))
                    ->ignore($section instanceof Section ? $section->getKey() : null),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'capacity' => $this->filled('capacity') ? $this->input('capacity') : null,
        ]);
    }
}
