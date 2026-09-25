<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the name is required.
 */
class GuardianRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = ['name' => trim((string) $this->input('name'))];

        foreach (['phone', 'email', 'occupation', 'address'] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        $values['email'] = $values['email'] === null ? null : strtolower($values['email']);

        $this->merge($values);
    }
}
