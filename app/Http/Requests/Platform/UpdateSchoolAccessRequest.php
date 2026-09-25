<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolAccessRequest extends FormRequest
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
            'menu_ids' => ['sometimes', 'array'],
            'menu_ids.*' => [
                'integer',
                'distinct',
                // Only pages can be assigned; group rows (no parent) are rejected.
                Rule::exists('menus', 'id')->where('is_active', true)->whereNotNull('parent_id'),
            ],
        ];
    }
}
