<?php

namespace App\Http\Requests\School;

use App\Models\Period;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PeriodRequest extends FormRequest
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
        $period = $this->route('period');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('periods', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('school_id', $schoolId))
                    ->ignore($period instanceof Period ? $period->getKey() : null),
            ],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'is_break' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'starts_at' => $this->filled('starts_at') ? substr((string) $this->input('starts_at'), 0, 5) : null,
            'ends_at' => $this->filled('ends_at') ? substr((string) $this->input('ends_at'), 0, 5) : null,
            'is_break' => $this->boolean('is_break'),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
        ]);
    }
}
