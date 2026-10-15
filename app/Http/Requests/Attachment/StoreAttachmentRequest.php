<?php

namespace App\Http\Requests\Attachment;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // entity-level authorization handled in controller
    }

    public function rules(): array
    {
        $maxKb = Attachment::MAX_SIZE_MB * 1024;

        return [
            'files'   => ['required', 'array', 'max:' . config('crm.attachments.max_per_upload', 5)],
            'files.*' => [
                'required', 'file',
                "max:{$maxKb}",
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv',
            ],
        ];
    }
}
