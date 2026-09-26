<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What a school administrator may change about their own school. Status, trial, billing,
 * limits and menus stay with the Super Admin.
 */
class SchoolSettingsRequest extends FormRequest
{
    /** Optional values kept in schools.settings. */
    public const SETTING_KEYS = ['principal_name', 'board', 'affiliation_no', 'website', 'established_year', 'admission_no_prefix', 'working_days'];

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'timezone' => ['required', 'timezone'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'board' => ['nullable', 'string', 'max:100'],
            'affiliation_no' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->format('Y')],
            'admission_no_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:0,6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admission_no_prefix.regex' => 'Use only letters and numbers, for example ADM.',
            'website.url' => 'Enter a full address such as https://www.example.com.',
            'working_days.required' => 'Choose at least one working day.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name', 'email', 'phone', 'address', 'timezone', 'principal_name', 'board', 'affiliation_no', 'website', 'established_year', 'admission_no_prefix'] as $field) {
            $value = trim((string) $this->input($field));
            $trimmed[$field] = $value === '' ? null : $value;
        }

        $trimmed['email'] = $trimmed['email'] === null ? null : strtolower($trimmed['email']);
        $trimmed['admission_no_prefix'] = $trimmed['admission_no_prefix'] === null ? null : strtoupper($trimmed['admission_no_prefix']);

        $this->merge($trimmed);
    }

    /**
     * The settings JSON to store: existing keys are kept, known keys are replaced.
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    public function settings(array $current): array
    {
        $validated = $this->validated();

        foreach (self::SETTING_KEYS as $key) {
            $current[$key] = $key === 'working_days'
                ? array_values(array_unique(array_map('intval', $validated['working_days'] ?? [])))
                : ($validated[$key] ?? null);
        }

        if ($current['established_year'] !== null) {
            $current['established_year'] = (int) $current['established_year'];
        }

        return $current;
    }
}
