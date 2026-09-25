<?php

namespace App\Http\Requests\Platform;

use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends StoreSchoolRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $school = $this->route('school');

        $rules['slug'] = [
            'nullable',
            'string',
            'max:255',
            'alpha_dash:ascii',
            Rule::unique('schools', 'slug')->ignore($school),
        ];
        $rules['code'] = [
            'nullable',
            'string',
            'max:50',
            'alpha_dash:ascii',
            Rule::unique('schools', 'code')->ignore($school),
        ];

        return $rules;
    }
}
