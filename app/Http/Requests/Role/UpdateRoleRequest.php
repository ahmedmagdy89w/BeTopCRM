<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Permission;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update-users');
    }

    public function rules(): array
    {
        $validPermissions = Permission::pluck('name')->all();

        return [
            'name' => ['sometimes', 'string', 'max:100', 'unique:roles,name,'.$this->route('role')?->id],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', $validPermissions)],
        ];
    }
}
