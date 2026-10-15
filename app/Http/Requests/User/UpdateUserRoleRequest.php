<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update-users');
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Role::pluck('name')->all())],
        ];
    }
}
