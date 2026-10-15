<?php

namespace App\Http\Requests\CompanySettings;

use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;

class UploadCompanyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', CompanySetting::class);
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png'],
        ];
    }
}
