<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user->getKey() : null),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', $this->passwordRule()],
            ...$this->accessRules(),
        ];
    }
}
