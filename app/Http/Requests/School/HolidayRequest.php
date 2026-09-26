<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'ends_on' => $this->filled('ends_on') ? $this->input('ends_on') : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ]);
    }

    /**
     * A one-day holiday keeps ends_on empty.
     *
     * @return array<string, mixed>
     */
    public function holidayData(): array
    {
        $data = $this->validated();

        if (($data['ends_on'] ?? null) === $data['starts_on']) {
            $data['ends_on'] = null;
        }

        return $data;
    }
}
