<?php

namespace App\Http\Requests\Platform;

use App\Models\Role;

/**
 * Type and school are fixed after creation so existing users never end up with a foreign role.
 */
class UpdateRoleRequest extends StoreRoleRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $schoolId = $role instanceof Role && $role->school_id !== null ? (int) $role->school_id : null;

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($schoolId, $role instanceof Role ? (int) $role->getKey() : null)],
            ...$this->sharedRules(),
        ];
    }
}
