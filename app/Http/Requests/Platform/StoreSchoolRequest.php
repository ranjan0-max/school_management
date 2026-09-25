<?php

namespace App\Http\Requests\Platform;

use App\Enums\SchoolStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('schools', 'slug')],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('schools', 'code')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'string', 'max:10', 'alpha_dash:ascii'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'status' => ['required', Rule::enum(SchoolStatus::class)],
            'trial_ends_at' => ['nullable', 'date'],
            'max_students' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'max_staff' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'price_per_student' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => $this->nullableTrimmed('slug'),
            'code' => $this->nullableUppercase('code'),
            'email' => $this->nullableLowercase('email'),
            'phone' => $this->nullableTrimmed('phone'),
            'address' => $this->nullableTrimmed('address'),
            'currency' => strtoupper(trim((string) $this->input('currency', 'INR'))),
            'locale' => trim((string) $this->input('locale', 'en')),
            'timezone' => trim((string) $this->input('timezone', 'Asia/Kolkata')),
            'trial_ends_at' => $this->nullableTrimmed('trial_ends_at'),
            'max_students' => $this->nullableTrimmed('max_students'),
            'max_staff' => $this->nullableTrimmed('max_staff'),
            'price_per_student' => $this->nullableTrimmed('price_per_student'),
            'billing_cycle' => $this->nullableTrimmed('billing_cycle'),
        ]);
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableUppercase(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : strtoupper($value);
    }

    private function nullableLowercase(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : strtolower($value);
    }
}
